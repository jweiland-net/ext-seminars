<?php

namespace OliverKlee\Seminars\Hooks;

use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This class holds functions used to validate submitted forms in the back end.
 *
 * These functions are called from DataHandler via hooks.
 *
 * @author Mario Rimann <typo3-coding@rimann.org>
 * @author Niels Pardon <mail@niels-pardon.de>
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class DataHandlerHook
{
    /**
     * @var string
     */
    const DATE_RENDER_FORMAT = 'Y-m-d\\TH:i:s\\Z';

    /**
     * @var string
     */
    const DATE_PARSE_FORMAT = 'Y-m-d?H:i:s?';

    /**
     * @var string[]
     */
    private $registeredTables = ['tx_seminars_seminars', 'tx_seminars_timeslots'];

    /**
     * @var array[]
     */
    private $tceMainFieldArrays = [];

    /**
     * Creates the time slots requested by the time slot wizard (if any are requested).
     *
     * @param DataHandler $dataHandler
     *
     * @return void
     */
    public function processDatamap_beforeStart(DataHandler $dataHandler)
    {
        $allData = &$dataHandler->datamap;
        if (!isset($allData['tx_seminars_seminars'])) {
            return;
        }

        if (!isset($allData['tx_seminars_timeslots'])) {
            $allData['tx_seminars_timeslots'] = [];
        }
        $allTimeSlots = &$allData['tx_seminars_timeslots'];

        $events = &$allData['tx_seminars_seminars'];
        foreach ($events as $eventUid => &$event) {
            $wizardConfiguration = (array)$event['time_slot_wizard'];
            unset($event['time_slot_wizard']);
            if (!$this->isValidTimeSlotConfiguration($wizardConfiguration)) {
                continue;
            }

            $this->createTimeSlots($event, $eventUid, $wizardConfiguration, $allTimeSlots);
        }
    }

    /**
     * Handles data after everything had been written to the database.
     *
     * @return void
     */
    public function processDatamap_afterAllOperations()
    {
        $this->processTimeSlots();
        $this->processEvents();
    }

    /**
     * Builds $this->tceMainFieldArrays if the right tables were modified.
     *
     * Some of the parameters of this function are not used in this function.
     * But they are given by the hook in DataHandler.
     *
     * Note: When using the hook after INSERT operations, you will only get the
     * temporary NEW... id passed to your hook as $id, but you can easily
     * translate it to the real uid of the inserted record using the
     * $pObj->substNEWwithIDs array.
     *
     * @param string $status the status of this record (new/update)
     * @param string $table the affected table name
     * @param string|int $uid the UID of the affected record (may be 0)
     * @param string[] &$fieldArray an array of all fields that got changed
     * @param DataHandler $dataHandler
     *
     * @return void
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array &$fieldArray,
        DataHandler $dataHandler
    ) {
        if (!\in_array($table, $this->registeredTables, true)) {
            return;
        }

        $realUid = $this->createRealUid($uid, $dataHandler);
        $this->tceMainFieldArrays[$table][$realUid] = $fieldArray;
    }

    /**
     * @param string|int $uid
     * @param DataHandler $dataHandler
     *
     * @return int
     */
    private function createRealUid($uid, DataHandler $dataHandler)
    {
        if ($this->isPersistedUid($uid)) {
            return (int)$uid;
        }

        return (int)$dataHandler->substNEWwithIDs[$uid];
    }

    /**
     * @param string|int $uid
     *
     * @return bool
     */
    private function isPersistedUid($uid)
    {
        return MathUtility::canBeInterpretedAsInteger($uid);
    }

    /**
     * Processes all time slots.
     *
     * @return void
     */
    private function processTimeSlots()
    {
        $table = 'tx_seminars_timeslots';
        if (!$this->hasDataForTable($table)) {
            return;
        }

        foreach ($this->tceMainFieldArrays[$table] as $uid => $_) {
            $this->processSingleTimeSlot((int)$uid);
        }
    }

    /**
     * @param string $table
     *
     * @return bool
     */
    private function hasDataForTable($table)
    {
        return isset($this->tceMainFieldArrays[$table]) && \is_array($this->tceMainFieldArrays[$table]);
    }

    /**
     * Processes all events.
     *
     * @return void
     */
    private function processEvents()
    {
        $table = 'tx_seminars_seminars';
        if (!$this->hasDataForTable($table)) {
            return;
        }

        foreach ($this->tceMainFieldArrays[$table] as $uid => $fieldArray) {
            $this->processSingleEvent((int)$uid, $fieldArray);
        }
    }

    /**
     * Processes a single time slot.
     *
     * @param int $uid
     *
     * @return void
     */
    private function processSingleTimeSlot($uid)
    {
        /** @var \Tx_Seminars_OldModel_TimeSlot $timeSlot */
        $timeSlot = GeneralUtility::makeInstance(\Tx_Seminars_OldModel_TimeSlot::class, $uid, false);

        if ($timeSlot->isOk()) {
            $timeSlot->saveToDatabase($timeSlot->getUpdateArray());
        }
    }

    /**
     * Processes a single event.
     *
     * @param int $uid
     * @param string[] $fieldArray an array of all fields that got changed
     *
     * @return void
     */
    private function processSingleEvent($uid, array $fieldArray)
    {
        /** @var \Tx_Seminars_OldModel_Event $event */
        $event = GeneralUtility::makeInstance(\Tx_Seminars_OldModel_Event::class, $uid, false, true);

        if ($event->isOk()) {
            $event->saveToDatabase($event->getUpdateArray($fieldArray));
        }
    }

    /**
     * @param array $configuration
     *
     * @return bool
     */
    private function isValidTimeSlotConfiguration(array $configuration)
    {
        $requiredFields = ['first_start', 'first_end', 'all', 'frequency', 'until'];
        foreach ($requiredFields as $field) {
            if (empty($configuration[$field])) {
                return false;
            }
        }

        $all = (int)$configuration['all'];
        $valid = $all > 0;

        $validFrequencies = ['daily', 'weekly', 'monthly', 'yearly'];
        $frequency = $configuration['frequency'];
        $valid = $valid && \in_array($frequency, $validFrequencies, true);

        $firstStart = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['first_start'])->getTimestamp();
        $firstEnd = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['first_end'])->getTimestamp();
        $until = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['until'])->getTimestamp();
        $valid = $valid && $firstStart > 0 && $firstEnd > $firstStart && $until > $firstStart;

        return $valid;
    }

    /**
     * @param array $event
     * @param string|int $eventUid
     * @param array $configuration
     * @param array $allTimeSlots
     */
    private function createTimeSlots(array &$event, $eventUid, array $configuration, array &$allTimeSlots)
    {
        if (!\class_exists(Rule::class)) {
            require_once __DIR__ . '/../../Resources/Private/Php/vendor/autoload.php';
        }

        if (isset($event['pid'])) {
            $eventPid = (int)$event['pid'];
        } else {
            $record = BackendUtility::getRecord('tx_seminars_seminars', $eventUid, 'pid');
            $eventPid = (int)$record['pid'];
        }

        $timeSlotUids = GeneralUtility::trimExplode(',', $event['timeslots']);

        $all = (int)$configuration['all'];
        $frequency = $configuration['frequency'];
        $firstStart = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['first_start']);
        $firstEnd = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['first_end']);
        $until = \DateTime::createFromFormat(self::DATE_RENDER_FORMAT, $configuration['until']);

        $frequencyCommand = \strtoupper($frequency);
        $rule = new Rule();
        $rule->setStartDate($firstStart)
            ->setEndDate($firstEnd)
            ->setFreq($frequencyCommand)
            ->setInterval($all)
            ->setUntil($until);

        $transformer = new ArrayTransformer();
        $recurrences = $transformer->transform($rule);
        foreach ($recurrences as $recurrence) {
            $temporaryUid = \uniqid('NEW', true);
            $timeSlotUids[] = $temporaryUid;
            $start = $recurrence->getStart();
            $end = $recurrence->getEnd();
            $formattedStart = $start->format(self::DATE_RENDER_FORMAT);
            $formattedEnd = $end->format(self::DATE_RENDER_FORMAT);
            $allTimeSlots[$temporaryUid] = [
                'pid' => $eventPid,
                'begin_date' => $formattedStart,
                'end_date' => $formattedEnd,
            ];
        }

        $event['timeslots'] = \implode(',', $timeSlotUids);
    }
}
