<?php

declare(strict_types=1);

use OliverKlee\PhpUnit\TestCase;
use OliverKlee\Seminars\BackEnd\GeneralEventMailForm;
use OliverKlee\Seminars\Tests\LegacyUnit\Support\Traits\BackEndTestsTrait;

/**
 * Test case.
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class Tx_Seminars_Tests_Unit_BackEnd_GeneralEventMailFormTest extends TestCase
{
    use BackEndTestsTrait;

    /**
     * @var GeneralEventMailForm
     */
    private $subject = null;

    /**
     * @var \Tx_Oelib_TestingFramework
     */
    private $testingFramework = null;

    protected function setUp()
    {
        $this->unifyTestingEnvironment();

        $this->testingFramework = new \Tx_Oelib_TestingFramework('tx_seminars');

        $dummySysFolderUid = $this->testingFramework->createSystemFolder();
        \Tx_Oelib_PageFinder::getInstance()->setPageUid($dummySysFolderUid);

        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'Dummy Organizer',
                'email' => 'foo@example.com',
            ]
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'organizers' => 1,
                'begin_date' => $GLOBALS['SIM_EXEC_TIME'] + 42,
                'title' => 'Dummy Event',
                'registrations' => 1,
            ]
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $eventUid,
            $organizerUid,
            'organizers'
        );

        $this->subject = new GeneralEventMailForm($eventUid);
    }

    protected function tearDown()
    {
        $this->testingFramework->cleanUp();
        $this->restoreOriginalEnvironment();
    }

    ///////////////////////////////////////////////
    // Tests regarding the rendering of the form.
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function renderContainsSubmitButton()
    {
        self::assertContains(
            '<button class="submitButton sendEmail"><p>' .
            $this->getLanguageService()->getLL('generalMailForm_sendButton') .
            '</p></button>',
            $this->subject->render()
        );
    }

    /**
     * @test
     */
    public function renderContainsPrefilledBodyFieldWithLocalizedSalutation()
    {
        self::assertContains('salutation', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderContainsTheCancelEventActionForThisForm()
    {
        self::assertContains(
            '<input type="hidden" name="action" value="sendEmail" />',
            $this->subject->render()
        );
    }
}
