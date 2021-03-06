<?php

declare(strict_types=1);

use OliverKlee\PhpUnit\TestCase;

/**
 * Test case.
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class Tx_Seminars_Tests_Unit_ViewHelper_CommaSeparatedTitlesTest extends TestCase
{
    /**
     * @var \Tx_Seminars_ViewHelper_CommaSeparatedTitles
     */
    private $subject;

    /**
     * @var \Tx_Oelib_TestingFramework
     */
    private $testingFramework;

    /**
     * @var \Tx_Oelib_List
     */
    private $list;

    /**
     * @var string
     */
    const TIME_FORMAT = '%H:%M';

    protected function setUp()
    {
        $this->testingFramework = new \Tx_Oelib_TestingFramework('tx_seminars');
        $this->list = new \Tx_Oelib_List();
        $this->subject = new \Tx_Seminars_ViewHelper_CommaSeparatedTitles();
    }

    protected function tearDown()
    {
        $this->testingFramework->cleanUp();
    }

    /**
     * @test
     */
    public function renderWithEmptyListReturnsEmptyString()
    {
        self::assertSame(
            '',
            $this->subject->render($this->list)
        );
    }

    /**
     * @test
     */
    public function renderWithElementsInListWithoutGetTitleMethodThrowsBadMethodCallException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All elements in $list must implement the interface Tx_Seminars_Interface_Titled.');
        $this->expectExceptionCode(1333658899);

        $model = new \Tx_Seminars_Tests_Unit_Fixtures_Model_UntitledTestingModel();
        $model->setData([]);

        $this->list->add($model);

        $this->subject->render($this->list);
    }

    /**
     * @test
     */
    public function renderWithOneElementListReturnsOneElementsTitle()
    {
        $model = new \Tx_Seminars_Tests_Unit_Fixtures_Model_TitledTestingModel();
        $model->setData(['title' => 'Testing model']);

        $this->list->add($model);

        self::assertSame(
            $model->getTitle(),
            $this->subject->render($this->list)
        );
    }

    /**
     * @test
     */
    public function renderWithTwoElementsListReturnsTwoElementTitlesSeparatedByComma()
    {
        $firstModel = new \Tx_Seminars_Tests_Unit_Fixtures_Model_TitledTestingModel();
        $firstModel->setData(['title' => 'First testing model']);
        $secondModel = new \Tx_Seminars_Tests_Unit_Fixtures_Model_TitledTestingModel();
        $secondModel->setData(['title' => 'Second testing model']);

        $this->list->add($firstModel);
        $this->list->add($secondModel);

        self::assertSame(
            $firstModel->getTitle() . ', ' . $secondModel->getTitle(),
            $this->subject->render($this->list)
        );
    }

    /**
     * @test
     */
    public function renderWithOneElementListReturnsOneElementsTitleHtmlspecialchared()
    {
        $model = new \Tx_Seminars_Tests_Unit_Fixtures_Model_TitledTestingModel();
        $model->setData(['title' => '<test>Testing model</test>']);

        $this->list->add($model);

        self::assertSame(
            \htmlspecialchars($model->getTitle(), ENT_QUOTES | ENT_HTML5),
            $this->subject->render($this->list)
        );
    }
}
