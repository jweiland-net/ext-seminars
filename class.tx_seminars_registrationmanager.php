<?php
/***************************************************************
* Copyright notice
*
* (c) 2005-2009 Oliver Klee (typo3-coding@oliverklee.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');

// This file doesn't include the locallang file in the BE because objectfromdb
// already does that.

/**
 * Class 'tx_seminars_registrationmanager' for the 'seminars' extension.
 *
 * This utility class checks and creates registrations for seminars.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class tx_seminars_registrationmanager extends tx_oelib_templatehelper {
	/** same as class name */
	public $prefixId = 'tx_seminars_registrationmanager';
	/**  path to this script relative to the extension dir */
	public $scriptRelPath = 'class.tx_seminars_registrationmanager.php';

	/**
	 * @var string the extension key
	 */
	public $extKey = 'seminars';

	/**
	 * @var tx_seminars_registration the current registration
	 */
	private $registration = null;

	/**
	 * @var boolean whether we have already initialized the templates
	 *              (which is done lazily)
	 */
	private $isTemplateInitialized = false;

	/**
	 * @var integer use text format for e-mails to attendees
	 */
	const SEND_TEXT_MAIL = 0;

	/**
	 * @var integer use HTML format for e-mails to attendees
	 */
	const SEND_HTML_MAIL = 1;

	/**
	 * @var integer use user-specific format for e-mails to attendees
	 */
	const SEND_USER_MAIL = 2;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Frees as much memory that has been used by this object as possible.
	 */
	public function __destruct() {
		if ($this->registration) {
			$this->registration->__destruct();
			unset($this->registration);
		}

		parent::__destruct();
	}

	/**
	 * Checks whether is possible to register for a given seminar at all:
	 * if a possibly logged-in user hasn't registered yet for this seminar,
	 * if the seminar isn't canceled, full etc.
	 *
	 * If no user is logged in, it is just checked whether somebody could register
	 * for this seminar.
	 *
	 * Returns true if everything is okay, false otherwise.
	 *
	 * This function even works if no user is logged in.
	 *
	 * @param object a seminar for which we'll check if it is possible to
	 *               register
	 *
	 * @return boolean true if everything is okay for the link, false otherwise
	 */
	public function canRegisterIfLoggedIn(tx_seminars_seminar $event) {
		if (!$event->canSomebodyRegister()) {
			return false;
		}
		if (!tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()) {
			return true;
		}

		return $this->couldThisUserRegister($event);
	}

	/**
	 * Checks whether is possible to register for a given seminar at all:
	 * if a possibly logged-in user hasn't registered yet for this seminar,
	 * if the seminar isn't canceled, full etc.
	 *
	 * If no user is logged in, it is just checked whether somebody could register
	 * for this seminar.
	 *
	 * Returns a message if there is anything to complain about
	 * and an empty string otherwise.
	 *
	 * This function even works if no user is logged in.
	 *
	 * Note: This function does not check whether a logged-in front-end user
	 * fulfills all requirements for an event.
	 *
	 * @param object a seminar for which we'll check if it is possible to
	 *               register
	 *
	 * @return string error message or empty string
	 */
	public function canRegisterIfLoggedInMessage(tx_seminars_seminar $seminar) {
		$result = '';

		if (tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()
			&& $this->isUserBlocked($seminar)
		) {
			// The current user is already blocked for this event.
			$result = $this->translate('message_userIsBlocked');
		} elseif (tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()
			&& !$this->couldThisUserRegister($seminar)
		) {
			// The current user can not register for this event (no multiple
			// registrations are possible and the user is already registered).
			$result = $this->translate('message_alreadyRegistered');
		} elseif (!$seminar->canSomebodyRegister()) {
			// it is not possible to register for this seminar at all (it is
			// canceled, full, etc.)
			$result = $seminar->canSomebodyRegisterMessage();
		}

		return $result;
	}

	/**
	 * Checks whether the current FE user (if any is logged in) could register
	 * for the current event, not checking the event's vacancies yet.
	 * So this function only checks whether the user is logged in and isn't
	 * blocked for the event's duration yet.
	 *
	 * Note: This function does not check whether a logged-in front-end user
	 * fulfills all requirements for an event.
	 *
	 * @param object a seminar for which we'll check if it is possible to
	 *               register
	 *
	 * @return boolean true if the user could register for the given event,
	 *                 false otherwise
	 */
	private function couldThisUserRegister(tx_seminars_seminar $seminar) {
		// A user can register either if the event allows multiple registrations
		// or the user isn't registered yet and isn't blocked either.
		return $seminar->allowsMultipleRegistrations()
			|| (
				!$this->isUserRegistered($seminar)
				&& !$this->isUserBlocked($seminar)
			);
	}

	/**
	 * Creates an HTML link to the registration or login page.
	 *
	 * @param tx_oelib_templatehelper the pi1 object with configuration data
	 * @param tx_seminars_seminar the seminar to create the registration link
	 *                            for
	 *
	 * @return string the HTML tag, will be empty if the event needs no
	 *                registration, nobody can register to this event or the
	 *                currently logged in user is already registered to this
	 *                event and the event does not allow multiple registrations
	 *                by one user
	 */
	public function getRegistrationLink(
		tx_oelib_templatehelper $plugin, tx_seminars_seminar $event
	) {
		if (!$event->needsRegistration()
			|| !$this->canRegisterIfLoggedIn($event)
		) {
			return '';
		}

		return $this->getLinkToRegistrationOrLoginPage($plugin, $event);
	}

	/**
	 * Creates an HTML link to either the registration page (if a user is
	 * logged in) or the login page (if no user is logged in).
	 *
	 * If $seminar has a separate details page, the link to that details page
	 * will be returned instead.
	 *
	 * Before you can call this function, you should make sure that the link
	 * makes sense (ie. the seminar still has vacancies, the user hasn't
	 * registered for this seminar etc.).
	 *
	 * @param tx_oelib_templatehelper an object for a live page
	 * @param tx_seminars_seminar a seminar for which we'll check if it is
	 *                            possible to register
	 *
	 * @return string HTML code with the link
	 */
	public function getLinkToRegistrationOrLoginPage(
		tx_oelib_templatehelper $plugin, tx_seminars_seminar $seminar
	) {
		$label = $this->getRegistrationLabel($plugin, $seminar);

		if ($seminar->hasSeparateDetailsPage()) {
			$result = $plugin->cObj->typolink(
				$label,
			 	$seminar->getDetailedViewLinkConfiguration($plugin)
			);
		} else {
			$result = $this->getLinkToStandardRegistrationOrLoginPage(
				$plugin, $seminar, $label
			);
		}

		return $result;
	}

	/**
	 * Creates the label for the registration link.
	 *
	 * @param tx_oelib_templatehelper an object for a live page
	 * @param tx_seminars_seminar a seminar to which the registration
	 *                            should relate
	 *
	 * @return string label for the registration link, will not be empty
	 */
	private function getRegistrationLabel(
		tx_oelib_templatehelper $plugin, tx_seminars_seminar $seminar
	) {
		if ($seminar->hasVacancies()) {
			if ($seminar->hasDate()) {
				$label = $plugin->translate('label_onlineRegistration');
			} else {
				$label = $plugin->translate('label_onlinePrebooking');
			}
		} else {
			if ($seminar->hasRegistrationQueue()) {
				$label = sprintf(
					$plugin->translate('label_onlineRegistrationOnQueue'),
					$seminar->getAttendancesOnRegistrationQueue()
				);
			} else {
				$label = $plugin->translate('label_onlineRegistration');
			}
		}

		return $label;
	}

	/**
	 * Creates an HTML link to either the registration page (if a user is
	 * logged in) or the login page (if no user is logged in).
	 *
	 * This function only creates the link to the standard registration or login
	 * page; it should not be used if the seminar has a separate details page.
	 *
	 * @param tx_oelib_templatehelper an object for a live page
	 * @param tx_seminars_seminar a seminar for which we'll check if it is
	 *                            possible to register
	 * @param string label for the link, will not be empty
	 *
	 * @return string HTML code with the link
	 */
	private function getLinkToStandardRegistrationOrLoginPage(
		tx_oelib_templatehelper $plugin, tx_seminars_seminar $seminar,
		$label
	) {
		if (tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()) {
			// provides the registration link
			$result = $plugin->cObj->getTypoLink(
				$label,
				$plugin->getConfValueInteger('registerPID'),
				array(
					'tx_seminars_pi1[seminar]' => $seminar->getUid(),
					'tx_seminars_pi1[action]' => 'register'
				)
			);
		} else {
			// provides the login link
			$result = $plugin->getLoginLink(
				$label,
				$plugin->getConfValueInteger('registerPID'),
				$seminar->getUid()
			);
		}

		return $result;
	}

	/**
	 * Creates an HTML link to the unregistration page (if a user is logged in).
	 *
	 * @param object a tslib_pibase object for a live page
	 * @param object a registration from which we'll get the UID for our
	 *               GET parameters
	 *
	 * @return string HTML code with the link
	 */
	public function getLinkToUnregistrationPage(
		tslib_pibase $plugin,
		tx_seminars_registration $registration
	) {
		return $plugin->cObj->getTypoLink(
			$plugin->translate('label_onlineUnregistration'),
			$plugin->getConfValueInteger('registerPID'),
			array(
				'tx_seminars_pi1[registration]' => $registration->getUid(),
				'tx_seminars_pi1[action]' => 'unregister'
			)
		);
	}

	/**
	 * Checks whether a seminar UID is valid,
	 * ie. a non-deleted and non-hidden seminar with the given number exists.
	 *
	 * This function can be called even if no seminar object exists.
	 *
	 * @param string a given seminar UID (may not neccessarily be an integer)
	 *
	 * @return boolean true the UID is valid, false otherwise
	 */
	public function existsSeminar($seminarUid) {
		// We can't use t3lib_div::makeInstanceClassName in this case as we
		// cannot use a class function when using a variable as class name.
		return tx_seminars_objectfromdb::recordExists(
			$seminarUid,
			SEMINARS_TABLE_SEMINARS
		);
	}

	/**
	 * Checks whether a seminar UID is valid,
	 * ie. a non-deleted and non-hidden seminar with the given number exists.
	 *
	 * This function can be called even if no seminar object exists.
	 *
	 * @param string a given seminar UID (may not neccessarily be an integer)
	 *
	 * @return string empty string if the UID is valid, else a localized error
	 *                message
	 */
	public function existsSeminarMessage($seminarUid) {
		/** This is empty as long as no error has occured. */
		$message = '';

		if (!tx_seminars_objectfromdb::recordExists(
				$seminarUid,
				SEMINARS_TABLE_SEMINARS
			)
		) {
			$message = $this->translate('message_wrongSeminarNumber');
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader(
				'Status: 404 Not Found'
			);
		}

		return $message;
	}

	/**
	 * Checks whether a front-end user is already registered for this seminar.
	 *
	 * This method must not be called when no front-end user is logged in!
	 *
	 * @param object a seminar for which we'll check if it is possible to
	 *               register
	 *
	 * @return boolean true if user is already registered, false otherwise.
	 */
	public function isUserRegistered(tx_seminars_seminar $seminar) {
		return $seminar->isUserRegistered($this->getFeUserUid());
	}

	/**
	 * Checks whether a certain user already is registered for this seminar.
	 *
	 * This method must not be called when no front-end user is logged in!
	 *
	 * @param object a seminar for which we'll check if it is possible to
	 *               register
	 *
	 * @return string empty string if everything is OK, else a localized error
	 *                message
	 */
	public function isUserRegisteredMessage(tx_seminars_seminar $seminar) {
		return $seminar->isUserRegisteredMessage($this->getFeUserUid());
	}

	/**
	 * Checks whether a front-end user is already blocked during the time for
	 * a given event by other booked events.
	 *
	 * For this, only events that forbid multiple registrations are checked.
	 *
	 * @param object a seminar for which we'll check whether the user already is
	 *               blocked by an other seminars
	 *
	 * @return boolean true if user is blocked by another registration, false
	 *                 otherwise
	 */
	private function isUserBlocked(tx_seminars_seminar $seminar) {
		return $seminar->isUserBlocked($this->getFeUserUid());
	}

	/**
	 * Checks whether the data the user has just entered is okay for creating
	 * a registration, e.g. mandatory fields are filled, number fields only
	 * contain numbers, the number of seats to register is not too high etc.
	 *
	 * Please note that this function doesn't create a registration - it just
	 * checks.
	 *
	 * @param object the seminar object (that's the seminar we would like to
	 *               register for), must not be null
	 * @param array associative array with the registration data the user has
	 *              just entered
	 *
	 * @return boolean true if the data is okay, false otherwise
	 */
	public function canCreateRegistration(
		tx_seminars_seminar $seminar, array $registrationData
	) {
		return $this->canRegisterSeats($seminar, $registrationData['seats']);
	}

	/**
	 * Checks whether a registration with a given number of seats could be
	 * created, ie. an actual number is given and there are at least that many
	 * vacancies.
	 *
	 * @param object the seminar object (that's the seminar we would like to
	 *               register for)
	 * @param string the number of seats to check (should be an integer, but we
	 *               can't be sure of this)
	 *
	 * @return boolean true if there are at least that many vacancies, false
	 *                 otherwise
	 */
	public function canRegisterSeats(tx_seminars_seminar $seminar, $numberOfSeats) {
		$numberOfSeats = trim($numberOfSeats);

		// If no number of seats is given, ie. the user has not entered anything
		// or the field is not shown at all, assume 1.
		if (($numberOfSeats == '') || ($numberOfSeats == '0')) {
			$numberOfSeats = '1';
		}

		$numberOfSeatsInt = intval($numberOfSeats);

		// Check whether we have a valid number
		if ($numberOfSeats == strval($numberOfSeatsInt)) {
			if ($seminar->hasUnlimitedVacancies()) {
				$result = true;
			} else {
				$result = ($seminar->hasRegistrationQueue()
					 || ($seminar->getVacancies() >= $numberOfSeatsInt)
				);
			}
		} else {
			$result = false;
		}

		return $result;
	}

	/**
	 * Creates a registration to $this->registration, writes it to DB,
	 * and notifies the organizer and the user (both via e-mail).
	 *
	 * The additional notifications will only be sent if this is enabled in the
	 * TypoScript setup (which is the default).
	 *
	 * @param object the seminar object (that's the seminar we would like to
	 *               register for)
	 * @param array associative array with the registration data the user has
	 *              just entered
	 * @param tslib_pibase live plugin object
	 */
	public function createRegistration(
		tx_seminars_seminar $seminar, array $registrationData,
		tslib_pibase $plugin
	) {
		$registrationClassname = t3lib_div::makeInstanceClassName(
			'tx_seminars_registration'
		);
		$this->registration = new $registrationClassname($plugin->cObj);
		$this->registration->setRegistrationData(
			$seminar,
			$this->getFeUserUid(),
			$registrationData
		);
		$this->registration->commitToDb();

		$seminar->calculateStatistics();

		if ($this->registration->isOnRegistrationQueue()) {
			$this->notifyAttendee(
				$this->registration,
				$plugin,
				'confirmationOnRegistrationForQueue'
			);
			$this->notifyOrganizers(
				$this->registration,
				'notificationOnRegistrationForQueue'
			);
		} else {
			$this->notifyAttendee($this->registration, $plugin, 'confirmation');
			$this->notifyOrganizers($this->registration, 'notification');
		}

		if ($this->getConfValueBoolean('sendAdditionalNotificationEmails')) {
			$this->registration->sendAdditionalNotification($this->registration);
		}
	}

	/**
	 * Removes the given registration (if it exists and if it belongs to the
	 * currently logged in FE user).
	 *
	 * @param integer the UID of the registration that should be removed
	 * @param tslib_pibase live plugin object
	 */
	public function removeRegistration($registrationUid, tslib_pibase $plugin) {
		if (tx_seminars_objectfromdb::recordExists(
				$registrationUid,
				SEMINARS_TABLE_ATTENDANCES
		)){
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				SEMINARS_TABLE_ATTENDANCES,
				SEMINARS_TABLE_ATTENDANCES . '.uid=' . $registrationUid .
					tx_oelib_db::enableFields(SEMINARS_TABLE_ATTENDANCES)
			);

			if ($dbResult) {
				$registrationClassname = t3lib_div::makeInstanceClassname(
					'tx_seminars_registration'
				);
				$this->registration = new $registrationClassname(
					$plugin->cObj,
					$dbResult
				);

				if ($this->registration->getUser() == $this->getFeUserUid()) {
					tx_oelib_db::update(
						SEMINARS_TABLE_ATTENDANCES,
						SEMINARS_TABLE_ATTENDANCES .
							'.uid = ' . $registrationUid,
						array(
							'hidden' => 1,
							'tstamp' => time()
						)
					);

					$this->notifyAttendee(
						$this->registration,
						$plugin,
						'confirmationOnUnregistration'
					);
					$this->notifyOrganizers(
						$this->registration,
						'notificationOnUnregistration'
					);

					$this->fillVacancies($plugin);
				}
			}
		}
	}

	/**
	 * Fills vacancies created through a unregistration with attendees from the
	 * registration queue.
	 *
	 * @param tslib_pibase live plugin object
	 */
	private function fillVacancies(tslib_pibase $plugin) {
		$seminar = $this->registration->getSeminarObject();
		$seminar->calculateStatistics();

		if ($seminar->hasVacancies()) {
			$vacancies = $seminar->getVacancies();

			$registrationBagBuilder = t3lib_div::makeInstance(
				'tx_seminars_registrationBagBuilder'
			);
			$registrationBagBuilder->limitToEvent($seminar->getUid());
			$registrationBagBuilder->limitToOnQueue();
			$registrationBagBuilder->limitToSeatsAtMost(
				$seminar->getVacancies()
			);

			$bag = $registrationBagBuilder->build();
			foreach ($bag as $registration) {
				if ($vacancies <= 0) {
					break;
				}

				if ($registration->getSeats() <= $vacancies) {
					tx_oelib_db::update(
						SEMINARS_TABLE_ATTENDANCES,
						'uid = ' . $registration->getUid(),
						array(
							'registration_queue' => 0
						)
					);
					$vacancies -= $registration->getSeats();

					$this->notifyAttendee(
						$registration,
						$plugin,
						'confirmationOnQueueUpdate'
					);
					$this->notifyOrganizers(
						$registration, 'notificationOnQueueUpdate'
					);

					if (
						$this->getConfValueBoolean(
							'sendAdditionalNotificationEmails'
						)
					) {
						$this->sendAdditionalNotification($registration);
					}
				}
			}
			$bag->__destruct();
		}
	}

	/**
	 * Checks if the logged-in user fulfills all requirements for registration
	 * for the event $event.
	 *
	 * A front-end user needs to be logged in when this function is called.
	 *
	 * @param tx_seminars_seminar the event to check
	 *
	 * @return boolean true if the user fulfills all requirements, false
	 *                 otherwise
	 */
	public function userFulfillsRequirements(tx_seminars_seminar $event) {
		if (!$event->hasRequirements()) {
			return true;
		}
		$missingTopics = $this->getMissingRequiredTopics($event);
		$result = $missingTopics->isEmpty();
		$missingTopics->__destruct();

		return $result;
	}

	/**
	 * Returns the event topics the user still needs to register for in order
	 * to be able to register for $event.
	 *
	 *
	 * @param tx_seminars_seminar the event to check
	 *
	 * @return tx_seminars_seminarbag the event topics which still need the
	 *                                users registration, may be empty
	 */
	public function getMissingRequiredTopics(tx_seminars_seminar $event) {
		$builder = t3lib_div::makeInstance('tx_seminars_seminarbagbuilder');
		$builder->limitToRequiredEventTopics($event->getTopicUid());
		$builder->limitToTopicsWithoutRegistrationByUser($this->getFeUserUid());

		return $builder->build();
	}

	/**
	 * Sends an e-mail to the attendee with a message concerning his/her
	 * registration or unregistration.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 * @param tslib_pibase a live page
	 * @param string prefix for the locallang key of the localized hello
	 *               and subject string, allowed values are:
	 *               - confirmation
	 *               - confirmationOnUnregistration
	 *               - confirmationOnRegistrationForQueue
	 *               - confirmationOnQueueUpdate
	 *               In the following the parameter is prefixed with
	 *               "email_" and postfixed with "Hello" or "Subject".
	 */
	public function notifyAttendee(
		tx_seminars_registration $registration,
		tslib_pibase $plugin, $helloSubjectPrefix = 'confirmation'
	) {
		if (!$this->getConfValueBoolean('send' . ucfirst($helloSubjectPrefix))) {
			return;
		}

		$event = $registration->getSeminarObject();
		if (!$event->hasOrganizers()) {
			return;
		}

		if (!$registration->hasExistingFrontEndUser()) {
			return;
		}

		$eMailNotification = t3lib_div::makeInstance('tx_oelib_Mail');
		$eMailNotification->addRecipient($registration->getFrontEndUser());
		$eMailNotification->setSender($event->getOrganizerBag()->current());
		$eMailNotification->setSubject(
			$this->translate('email_' . $helloSubjectPrefix . 'Subject') . ': ' .
				$event->getTitleAndDate('-')
		);

		$this->initializeTemplate();

		$mailFormat = tx_oelib_configurationProxy::getInstance('seminars')
			->getConfigurationValueInteger('eMailFormatForAttendees');
		if (($mailFormat == self::SEND_HTML_MAIL)
			|| (($mailFormat == self::SEND_USER_MAIL)
				&& $registration->getFrontEndUser()->wantsHtmlEMail())
		) {
			$eMailNotification->setCssFile(
				$this->getConfValueString('cssFileForAttendeeMail')
			);
			$eMailNotification->setHTMLMessage(
				$this->buildEmailContent(
					$registration, $plugin, $helloSubjectPrefix, true
				)
			);
		}

		$eMailNotification->setMessage(
			$this->buildEmailContent($registration, $plugin, $helloSubjectPrefix)
		);

		tx_oelib_mailerFactory::getInstance()->getMailer()->send(
			$eMailNotification
		);

		$eMailNotification->__destruct();
	}

	/**
	 * Sends an e-mail to all organizers with a message about a registration or
	 * unregistration.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 * @param string prefix for the locallang key of the localized hello
	 *               and subject string, allowed values are:
	 *               - notification
	 *               - notificationOnUnregistration
	 *               - notificationOnRegistrationForQueue
	 *               - notificationOnQueueUpdate
	 *               In the following the parameter is prefixed with
	 *               "email_" and postfixed with "Hello" or "Subject".
	 */
	public function notifyOrganizers(
		tx_seminars_registration $registration,
		$helloSubjectPrefix = 'notification'
	) {
		if (!$this->getConfValueBoolean('send' . ucfirst($helloSubjectPrefix))) {
			return;
		}

		$event = $registration->getSeminarObject();
		if (!$event->hasOrganizers()) {
			return;
		}

		if (!$registration->hasExistingFrontEndUser()) {
			return;
		}

		$eMailNotification = t3lib_div::makeInstance('tx_oelib_Mail');
		$eMailNotification->setSender($registration->getFrontEndUser());

		foreach ($event->getOrganizerBag() as $organizer) {
			$eMailNotification->addRecipient($organizer);
		}

		$eMailNotification->setSubject(
			$this->translate('email_' . $helloSubjectPrefix . 'Subject') .
				': ' . $registration->getTitle()
		);

		$this->initializeTemplate();
		$this->hideSubparts(
			$this->getConfValueString('hideFieldsInNotificationMail'),
			'field_wrapper'
		);

		$this->setMarker(
			'hello',
			$this->translate('email_' . $helloSubjectPrefix . 'Hello')
		);
		$this->setMarker('summary', $registration->getTitle());

		if ($this->hasConfValueString('showSeminarFieldsInNotificationMail')) {
			$this->setMarker(
				'seminardata',
				$event->dumpSeminarValues(
					$this->getConfValueString(
						'showSeminarFieldsInNotificationMail'
					)
				)
			);
		} else {
			$this->hideSubparts('seminardata', 'field_wrapper');
		}

		if ($this->hasConfValueString('showFeUserFieldsInNotificationMail')) {
			$this->setMarker(
				'feuserdata',
				$registration->dumpUserValues(
					$this->getConfValueString('showFeUserFieldsInNotificationMail')
				)
			);
		} else {
			$this->hideSubparts('feuserdata', 'field_wrapper');
		}

		if ($this->hasConfValueString('showAttendanceFieldsInNotificationMail')) {
			$this->setMarker(
				'attendancedata',
				$registration->dumpAttendanceValues(
					$this->getConfValueString(
						'showAttendanceFieldsInNotificationMail'
					)
				)
			);
		} else {
			$this->hideSubparts('attendancedata', 'field_wrapper');
		}

		$eMailNotification->setMessage($this->getSubpart('MAIL_NOTIFICATION'));

		tx_oelib_mailerFactory::getInstance()->getMailer()->send(
			$eMailNotification
		);

		$eMailNotification->__destruct();
	}

	/**
	 * Checks if additional notifications to the organizers are necessary.
	 * In that case, the notification e-mails will be sent to all organizers.
	 *
	 * Additional notifications mails will be sent out upon the following events:
	 * - an event now has enough registrations
	 * - an event is fully booked
	 * If both things happen at the same time (minimum and maximum count of
	 * attendees are the same), only the "event is full" message will be sent.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 */
	public function sendAdditionalNotification(
		tx_seminars_registration $registration
	) {
		if ($registration->isOnRegistrationQueue()) {
			return;
		}

		$emailReason = $this->getReasonForNotification($registration);
		if ($emailReason == '') {
			return;
		}

		$event = $registration->getSeminarObject();
		$eMail = t3lib_div::makeInstance('tx_oelib_Mail');

		$eMail->setSender($event->getOrganizerBag()->current());
		$eMail->setMessage($this->getMessageForNotification($registration, $emailReason));
		$eMail->setSubject(sprintf(
			$this->translate(
				'email_additionalNotification' . $emailReason . 'Subject'
			),
			$event->getUid(),
			$event->getTitleAndDate('-')
		));

		foreach ($event->getOrganizerBag() as $organizer) {
			$eMail->addRecipient($organizer);
		}

		tx_oelib_mailerFactory::getInstance()->getMailer()->send($eMail);

		$eMail->__destruct();
	}

	/**
	 * Returns the topic for the additional notification e-mail.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 *
	 * @return string "EnoughRegistrations" if the event has enough attendances,
	 *                "IsFull" if the event is fully booked, otherwise an empty
	 *                string
	 */
	private function getReasonForNotification(tx_seminars_registration $registration) {
		$result = '';

		$event = $registration->getSeminarObject();
		if ($event->isFull()) {
			$result = 'IsFull';
		// Using "==" instead of ">=" ensures that only one set of e-mails is
		// sent to the organizers.
		// This also ensures that no e-mail is send when minAttendances is 0
		// since this function is only called when at least one registration
		// is present.
		} elseif ($event->getAttendances() == $event->getAttendancesMin()) {
			$result = 'EnoughRegistrations';
		}

		return $result;
	}

	/**
	 * Returns the message for an e-mail according to the reason
	 * $reasonForNotification provided.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 * @param string reason for the notification, must be either "IsFull" or
	 *               "EnoughRegistrations", must not be empty
	 */
	private function getMessageForNotification(
		tx_seminars_registration $registration, $reasonForNotification
	) {
		$localllangKey = 'email_additionalNotification' . $reasonForNotification;
		$this->initializeTemplate();

		$this->setMarker('message', $this->translate($localllangKey));
		$showSeminarFields = $this->getConfValueString(
			'showSeminarFieldsInNotificationMail'
		);
		if ($showSeminarFields != '') {
			$this->setMarker(
				'seminardata',
				$registration->getSeminarObject()->dumpSeminarValues(
					$showSeminarFields
				)
			);
		} else {
			$this->hideSubparts('seminardata', 'field_wrapper');
		}

		return $this->getSubpart('MAIL_ADDITIONALNOTIFICATION');
	}

	/**
	 * Reads and initializes the templates.
	 * If this has already been called for this instance, this function does
	 * nothing.
	 *
	 * This function will read the template file as it is set in the TypoScript
	 * setup. If there is a template file set in the flexform of pi1, this will
	 * be ignored!
	 */
	private function initializeTemplate() {
		if (!$this->isTemplateInitialized) {
			$this->getTemplateCode(true);
			$this->setLabels();

			$this->isTemplateInitialized = true;
		}
	}

	/**
	 * Builds the e-mail body for an e-mail to the attendee.
	 *
	 * @param tx_seminars_registration the registration for which the
	 *                                 notification should be send
	 * @param tslib_pibase a live plugin
	 * @param string prefix for the locallang key of the localized hello
	 *               and subject string, allowed values are:
	 *               - confirmation
	 *               - confirmationOnUnregistration
	 *               - confirmationOnRegistrationForQueue
	 *               - confirmationOnQueueUpdate
	 *               In the following the parameter is prefixed with
	 *               "email_" and postfixed with "Hello" or "Subject".
	 * @param boolean whether to create a HTML body for the e-mail or just the
	 *                plain text version
	 *
	 * @return string the e-mail body for the attendee e-mail, will not be empty
	 */
	private function buildEmailContent(
		tx_seminars_registration $registration,
		tslib_pibase $plugin, $helloSubjectPrefix , $useHtml = false
	) {
		$wrapperPrefix = (($useHtml) ? 'html_' : '') . 'field_wrapper';

		$this->setMarker('html_mail_charset', (
			$GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']
				? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']
				: 'ISO-8859-1'
			)
		);
		$this->hideSubparts(
			$this->getConfValueString('hideFieldsInThankYouMail'),
			$wrapperPrefix
		);

		$hello = sprintf(
			$this->translate('email_' . $helloSubjectPrefix . 'Hello'),
			$registration->getUserName()
		);
		$this->setMarker('hello', (($useHtml) ? nl2br($hello) : $hello));
		$event = $registration->getSeminarObject();
		if ($event->hasEventType()) {
			$this->setMarker('event_type', $event->getEventType());
		} else {
			$this->hideSubparts('event_type', $wrapperPrefix);
		}
		$this->setMarker('title', $event->getTitle());
		$this->setMarker('uid', $event->getUid());

		$this->setMarker('registration_uid', $registration->getUid());

		if ($registration->hasSeats()) {
			$this->setMarker('seats', $registration->getSeats());
		} else {
			$this->hideSubparts('seats', $wrapperPrefix);
		}

		if ($registration->hasAttendeesNames()) {
			$this->setMarker('attendees_names', $registration->getAttendeesNames());
		} else {
			$this->hideSubparts('attendees_names', $wrapperPrefix);
		}

		if ($registration->hasLodgings()) {
			$this->setMarker('lodgings', $registration->getLodgings());
		} else {
			$this->hideSubparts('lodgings', $wrapperPrefix);
		}

		if ($registration->hasFoods()) {
			$this->setMarker('foods', $registration->getFoods());
		} else {
			$this->hideSubparts('foods', $wrapperPrefix);
		}

		if ($registration->hasCheckboxes()) {
			$this->setMarker('checkboxes', $registration->getCheckboxes());
		} else {
			$this->hideSubparts('checkboxes', $wrapperPrefix);
		}

		if ($registration->hasKids()) {
			$this->setMarker('kids', $registration->getNumberOfKids());
		} else {
			$this->hideSubparts('kids', $wrapperPrefix);
		}

		if ($event->hasAccreditationNumber()) {
			$this->setMarker(
				'accreditation_number',
				$event->getAccreditationNumber()
			);
		} else {
			$this->hideSubparts('accreditation_number', $wrapperPrefix);
		}

		if ($event->hasCreditPoints()) {
			$this->setMarker(
				'credit_points',
				$event->getCreditPoints()
			);
		} else {
			$this->hideSubparts('credit_points', $wrapperPrefix);
		}

		$this->setMarker('date',
			$event->getDate(
				(($useHtml) ? '&#8212;' : '-')
			)
		);
		$this->setMarker('time',
			$event->getTime(
				(($useHtml) ? '&#8212;' : '-')
			)
		);
		$this->setMarker('place', $event->getPlaceShort());

		if ($event->hasRoom()) {
			$this->setMarker('room', $event->getRoom());
		} else {
			$this->hideSubparts('room', $wrapperPrefix);
		}

		if ($registration->hasPrice()) {
			$this->setMarker('price', $registration->getPrice());
		} else {
			$this->hideSubparts('price', $wrapperPrefix);
		}

		if ($registration->hasTotalPrice()) {
			$this->setMarker('total_price', $registration->getTotalPrice(' '));
		} else {
			$this->hideSubparts('total_price', $wrapperPrefix);
		}

		// We don't need to check $this->seminar->hasPaymentMethods() here as
		// method_of_payment can only be set (using the registration form) if
		// the event has at least one payment method.
		if ($registration->hasMethodOfPayment()) {
			$this->setMarker(
				'paymentmethod',
				$event->getSinglePaymentMethodPlain(
					$registration->getMethodOfPaymentUid()
				)
			);
		} else {
			$this->hideSubparts('paymentmethod', $wrapperPrefix);
		}

		$this->setMarker('billing_address', $registration->getBillingAddress());

		$this->setMarker(
			'url',
			(($useHtml)
				? htmlspecialchars($event->getDetailedViewUrl($plugin))
				: $event->getDetailedViewUrl($plugin)
			)
		);

		if ($event->isPlanned()) {
			$this->unhideSubparts('planned_disclaimer', $wrapperPrefix);
		} else {
			$this->hideSubparts('planned_disclaimer', $wrapperPrefix);
		}

		$footers = $event->getOrganizersFooter();
		$this->setMarker('footer', $footers[0]);

		return $this->getSubpart(
			(($useHtml) ? 'MAIL_THANKYOU_HTML' : 'MAIL_THANKYOU')
		);
	}

	/**
	 * Checks whether the given event allows registration, as far as its date
	 * is concerned.
	 *
	 * @param tx_seminars_seminar the event to check the registration for
	 *
	 * @return boolean true if the event allows registration by date, false
	 *                 otherwise
	 */
	public function allowsRegistrationByDate(tx_seminars_seminar $event) {
		return ($event->hasDate())
			? !$event->isRegistrationDeadlineOver()
			: $event->getConfValueBoolean(
				'allowRegistrationForEventsWithoutDate'
			);
	}

	/**
	 * Checks whether the given event allows registration as far as the
	 * number of vacancies are concerned.
	 *
	 * @param tx_seminars_seminar the event to check the registration for
	 *
	 * @return boolean true if the event has enough seats for registration,
	 *                 false otherwise
	 */
	public function allowsRegistrationBySeats(tx_seminars_seminar $event) {
		return $event->hasRegistrationQueue() || $event->hasUnlimitedVacancies()
			|| $event->hasVacancies();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_registrationmanager.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_registrationmanager.php']);
}
?>