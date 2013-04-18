<?php
namespace Acme\Bundle\TestsBundle\Tests\Selenium;

use PHPUnit_Extensions_Selenium2TestCase_Keys as Keys;

class GridTest extends \PHPUnit_Extensions_Selenium2TestCase
{
    const TIME_OUT  = 1000;
    const MAX_AJAX_EXECUTION_TIME = 5000;

    protected function setUp()
    {
        $this->setHost(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_HOST);
        $this->setPort(intval(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_PORT));
        $this->setBrowser(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM2_BROWSER);
        $this->setBrowserUrl(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_TESTS_URL);
    }

    protected function tearDown()
    {
        $this->cookie()->clear();
    }

    protected function waitPageToLoad()
    {
        $this->waitUntil(
            function ($testCase) {
                $status = $testCase->execute(array('script' => "return 'complete' == document['readyState']", 'args' => array()));
                if ($status) {
                    return true;
                } else {
                    return null;
                }
            },
            self::MAX_AJAX_EXECUTION_TIME
        );

        $this->timeouts()->implicitWait(self::TIME_OUT);
    }

    protected function waitForAjax()
    {
        $this->waitUntil(
            function ($testCase) {
                $status = $testCase->execute(array('script' => 'return jQuery.active == 0', 'args' => array()));
                if ($status) {
                    return true;
                } else {
                    return null;
                }
            },
            self::MAX_AJAX_EXECUTION_TIME
        );

        $this->timeouts()->implicitWait(self::TIME_OUT);
    }

    protected function login($form)
    {
        $this->currentWindow()->maximize();
        $name = $form->byId('prependedInput');
        $password = $form->byId('prependedInput2');
        $name->clear();
        $name->value(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_LOGIN);
        $password->clear();
        $password->value(PHPUNIT_TESTSUITE_EXTENSION_SELENIUM_PASS);
        $form->clickOnElement('_submit');
        $form->waitPageToLoad();
    }

    public function testSelectPage()
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->waitForAjax();
        //open add new usr page
        $userData = $this->getRandomUser();
        $this->assertTrue($this->userExists($userData));
        $this->selectPage(2);
        $this->assertFalse($this->userExists($userData));
        $this->selectPage(1);
        $this->assertTrue($this->userExists($userData));
        $res = $this->getPagesCount();
        $res = $this->getRecordsCount();
    }

    public function testNextPage()
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->waitForAjax();
        //open add new usr page
        $userData = $this->getRandomUser();
        $this->assertTrue($this->userExists($userData));
        $this->nextPage();
        $this->assertFalse($this->userExists($userData));
        $this->previousPage();
        $this->assertTrue($this->userExists($userData));
    }

    public function testPrevPage()
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->waitForAjax();
        //open add new usr page
        $userData = $this->getRandomUser();
        $this->assertTrue($this->userExists($userData));
        $this->nextPage();
        $this->assertFalse($this->userExists($userData));
        $this->previousPage();
        $this->assertTrue($this->userExists($userData));
    }

    /**
     * @dataProvider filterData
     */
    public function testFilterBy($filterName, $condition)
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->waitForAjax();
        //open add new usr page
        $userData = $this->getRandomUser();
        $this->assertTrue($this->userExists($userData));
        $this->selectFilter($filterName, $userData[strtoupper($filterName)], $condition);
        $this->assertTrue($this->userExists($userData));
        $this->assertCount(1, $this->getRecordsOnPage());
        $this->clearFilter($filterName);
    }

    /**
     * @return array
     */
    public function filterData()
    {
        return array(
            'ID' => array('ID', '='),
            'Username' => array('Username', 'is equal to'),
            'Email' => array('Email', 'contains'),
            //'First name' => array('First name', 'is equal to'),
            //'Birthday' => array('Birthday', '')
        );
    }

    public function testAddFilter()
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->waitForAjax();
        //open add new usr page
        $userData = $this->getRandomUser();
        $this->assertTrue($this->userExists($userData));
        $countOfRecords = $this->getRecordsCount();
        $this->addFilter('Company');
        $this->selectFilter('Company', $userData[strtoupper('Company')], 'is equal to');
        $this->assertLessThan($countOfRecords, $this->getRecordsCount());
        $this->removeFilter('Company');
        $this->assertEquals($countOfRecords, $this->getRecordsCount());
    }

    /**
     * Select random user from current page
     *
     * @param int $pageSize
     * @return array
     */
    protected function getRandomUser($pageSize = 25)
    {
        $userId = rand(0, $pageSize-1);
        $users = $this->getRecordsOnPage();
        $user = $users[$userId]->elements($this->using('xpath')->value("td"));
        $columns = $this->elements($this->using('xpath')->value("//table/thead/tr/th"));
        $userData = array();
        for ($i=0; $i< count($columns); $i++) {
            $userData[$columns[$i]->text()] = $user[$i]->text();
        }
        return $userData;
    }

    /**
     * Change current page to specific
     *
     * @param int $page
     */
    protected function selectPage($page = 1)
    {
        $paginator = $this->byXPath("//div[contains(@class,'pagination')]/ul//input");
        //set focus
        $paginator->click();
        $this->clearInput($paginator);
        $paginator->value($page);
        $this->keysSpecial('enter');
        $this->waitForAjax();
    }

    /**
     * Navigate to next page
     */
    protected function nextPage()
    {
        $this->byXPath("//div[contains(@class,'pagination')]//a[contains(.,'Next')]")->click();
        $this->waitForAjax();
    }

    /**
     * Navigate to previous page
     */
    protected function previousPage()
    {
        $this->byXPath("//div[contains(@class,'pagination')]//a[contains(.,'Prev')]")->click();
        $this->waitForAjax();
    }

    /**
     * Get current page number
     *
     * @return string
     */
    protected function getCurrentPage()
    {
        return $this->byXPath("//div[contains(@class,'pagination')]/ul//input")->value();
    }

    /**
     * Get records count in grid by parsing text label
     *
     * @return string
     */
    protected function getRecordsCount()
    {
        $pager = $this->byXPath("//div[contains(@class,'pagination')]//label[@class='dib'][2]")->text();
        preg_match('/of\s+(\d+)\s+\|\s+(\d+)\s+records/i', $pager, $result);
        return intval($result[2]);
    }

    /**
     * Get pages count by parsing text label
     *
     * @return string
     */
    protected function getPagesCount()
    {
        $pager = $this->byXPath("//div[contains(@class,'pagination')]//label[@class='dib'][2]")->text();
        preg_match('/of\s+(\d+)\s+\|\s+(\d+)\s+records/i', $pager, $result);
        return intval($result[1]);
    }

    /**
     * Get all element from current page
     *
     * @return array PHPUnit_Extensions_Selenium2TestCase_Element
     */
    protected function getRecordsOnPage()
    {
        $records = $this->elements($this->using('xpath')->value("//table/tbody/tr"));
        return $records;
    }

    /**
     * Verify user exist on the current page
     *
     * @param array $userData
     * @return bool
     */
    protected function userExists($userData)
    {
        $xpath = '';
        foreach ($userData as $userField) {
            if ($xpath != '') {
                $xpath .= " and ";
            }
            $xpath .=  "td[contains(.,'{$userField}')]";
        }
        $xpath = "//table/tbody/tr[{$xpath}]";
        return $this->isElementPresent($xpath);
    }

    /**
     * Remove filter
     *
     * @param string $filterName
     */
    protected function removeFilter($filterName)
    {
        $this->byXPath(
            "//div[contains(@class, 'filter-box')]/div[contains(@class, 'filter-item')]"
            . "[button[contains(.,'{$filterName}')]]/a[contains(., 'Close')]"
        )->click();
        $this->waitForAjax();
    }

    /**
     * Add filter
     *
     * @param string $filterName
     */
    protected function addFilter($filterName)
    {
        $addFilter = $this->byXPath("//div[contains(@class, 'filter-box')]/button[contains(.,'Add filter')]");
        //expand filter list
        $addFilter->click();
        $filter = $this->byXPath("//input[@title='{$filterName}'][@name='multiselect_add-filter-select']");
        if (!$filter->selected()) {
            $filter->click();
        }
        $this->waitForAjax();
        //hide filter list
        $addFilter->click();
    }

    /**
     * Apply specific filter for current grid
     *
     * @param string $filterName
     * @param string $value
     * @param string $condition
     */
    protected function selectFilter($filterName, $value = '', $condition = '')
    {
        $this->byXPath(
            "//div[contains(@class, 'filter-box')]/div[contains(@class, 'filter-item')]"
            . "/button[contains(.,'{$filterName}')]"
        )->click();

        $criteria = $this->byXPath(
            "//div[contains(@class, 'filter-box')]/div[contains(@class, 'filter-item')]"
            . "[button[contains(.,'{$filterName}')]]/div[contains(@class, 'filter-criteria')]"
        );
        $input = $criteria->element($this->using('xpath')->value("div/div/input[@name='value']"));

        $input->clear();
        $input->value($value);

        //select criteria
        if ($condition != '') {
            $criteria->element($this->using('xpath')->value("div/div/div[label[text()='{$condition}']]/input"))->click();
        }
        $criteria->element($this->using('xpath')->value("div/div/div/button[contains(@class, 'filter-update')]"))->click();
        $this->waitForAjax();
    }

    /**
     * Clear filter value and apply
     *
     * @param string $filterName
     */
    protected function clearFilter($filterName)
    {
        $this->selectFilter($filterName);
    }

    /**
     * Clear input element when standard clear() does not help
     *
     * @param $element \PHPUnit_Extensions_Selenium2TestCase_Element
     */
    protected function clearInput($element)
    {
        $element->value('');
        $tx = $element->value();
        while ($tx!="") {
            $this->keysSpecial('backspace');
            $tx = $element->value();
        }
    }

    /**
     * Verify element present
     *
     * @param string $locator
     * @return bool
     */
    public function isElementPresent($locator)
    {
        try {
            $this->byXPath($locator);
            return true;
        } catch (\PHPUnit_Extensions_Selenium2TestCase_WebDriverException $e) {
            return false;
        }
    }

    /**
     * Get grid headers
     *
     * @return array
     */
    protected function getHeadersOnPage()
    {
        $records = $this->elements($this->using('xpath')->value("//table/thead/tr/th"));
        return $records;
    }

    /**
     * Get header Id
     *
     * @param array $records
     * @param string $headerName
     * @return int
     */
    protected function getHeaderId($records, $headerName)
    {
        $i = 1;
        foreach ($records as $column) {
            $name = $column->text();
            if (mb_strtoupper($headerName) == $name) {
                break;
            } else {
                $i++;
            }
        }
        return $i;
    }

    /**
     * Get grid column data
     *
     * @param int $columnId
     * @return array
     */
    protected function getColumnData($columnId)
    {
        $columnData = $this->elements($this->using('xpath')->value("//table/tbody/tr/td[$columnId]"));
        $rowData = array();
        foreach ($columnData as $value) {
            $fieldValue = $value->text();
            $rowData[] = $fieldValue;
        }
        return $rowData;
    }
    /**
     * Change grid column order
     *
     * @param string @columnName
     */
    protected function changeColumnSorting($columnName)
    {
        $this->byXPath("//table/thead/tr/th/a[contains(., \"$columnName\")]")->click();
        $this->waitForAjax();
    }

    /**
     * Set grid size to 100
     */
    protected function openMaxGridSize()
    {
        $this->byXPath("//*[@class='page-size pull-right form-horizontal']//button")->click();
        $this->byXPath("//*[@class='page-size pull-right form-horizontal']//a[contains(., '100')]")->click();
        $this->waitForAjax();
    }

    /**
     * Tests that order in columns works correct
     *
     * @param string $columnName
     * @dataProvider columnTitle
     */
    public function testSorting($columnName)
    {
        $this->url('user');
        $this->waitPageToLoad();
        $this->login($this);
        $this->openMaxGridSize();
        $columns = $this->getHeadersOnPage();
        $columnId = $this->getHeaderId($columns, $columnName);
        //test ascending order
        $this->changeColumnSorting($columnName);
        $columnOrder = $this->getColumnData($columnId);
        if ($columnName == 'Birthday') {
            $dateArray = array();
            foreach ($columnOrder as $value) {
                $date = strtotime($value);
                $dateArray[] = $date;
            }
            $columnOrder = $dateArray;
            $sortedColumnOrder = $columnOrder;
            sort($sortedColumnOrder);
        } else {
            $sortedColumnOrder = $columnOrder;
            natcasesort($sortedColumnOrder);
        }
        $this->assertEquals($columnOrder, $sortedColumnOrder, "Arrays doesn't match");
        //test descending order
        $this->changeColumnSorting($columnName);
        $columnOrder = $this->getColumnData($columnId);
        if ($columnName == 'Birthday') {
            $dateArray = array();
            foreach ($columnOrder as $value) {
                $date = strtotime($value);
                $dateArray[] = $date;
            }
            $columnOrder = $dateArray;
            $sortedColumnOrder = $columnOrder;
            sort($sortedColumnOrder);
        } else {
            $sortedColumnOrder = $columnOrder;
            natcasesort($sortedColumnOrder);
        }
        $sortedColumnOrder = array_reverse($sortedColumnOrder);
        $this->assertEquals($columnOrder, $sortedColumnOrder, "Arrays doesn't match");
    }

    /**
     * @return array
     */
    public function columnTitle()
    {
        return array(
            'ID' => array('ID'),
            'Username' => array('Username'),
            'Email' => array('Email'),
            'Birthday' => array('Birthday'),
            'Company' => array('Company'),
            'Salary' => array('Salary'),
        );
    }
}
