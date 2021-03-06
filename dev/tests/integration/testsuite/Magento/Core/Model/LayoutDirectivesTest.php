<?php
/**
 * Set of tests of layout directives handling behavior
 *
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\Core\Model;

class LayoutDirectivesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test scheduled operations in the rendering of elements
     *
     * Expected behavior:
     * 1) block1 was not declared at the moment when "1" invocation declared. The operation is scheduled
     * 2) block1 creation directive schedules adding "2" as well
     * 3) block2 is generated with "3"
     * 4) yet another action schedules replacing value of block2 into "4"
     * 5) when entire layout is read, all scheduled operations are executed in the same order as declared
     *    (blocks are instantiated first, of course)
     * The end result can be observed in container1
     *
     * @magentoAppIsolation enabled
     */
    public function testRenderElement()
    {
        $layout = $this->_getLayoutModel('render.xml');
        $this->assertEmpty($layout->renderElement('nonexisting_element'));
        $this->assertEquals('124', $layout->renderElement('container1'));
        $this->assertEquals('12', $layout->renderElement('block1'));
    }

    /**
     * Invoke getBlock() while layout is being generated
     *
     * Assertions in this test are pure formalism. The point is to emulate situation where block refers to other block
     * while the latter hasn't been generated yet, and assure that there is no crash
     */
    public function testGetBlockUnscheduled()
    {
        $layout = $this->_getLayoutModel('get_block.xml');
        $this->assertInstanceOf('Magento\Core\Block\Text', $layout->getBlock('block1'));
        $this->assertInstanceOf('Magento\Core\Block\Text', $layout->getBlock('block2'));
    }

    /**
     * @expectedException \Magento\Exception
     */
    public function testGetBlockUnscheduledException()
    {
        $this->_getLayoutModel('get_block_exception.xml');
    }

    public function testLayoutArgumentsDirective()
    {
        $layout = $this->_getLayoutModel('arguments.xml');
        $this->assertEquals('1', $layout->getBlock('block_with_args')->getOne());
        $this->assertEquals('two', $layout->getBlock('block_with_args')->getTwo());
        $this->assertEquals('3', $layout->getBlock('block_with_args')->getThree());
    }

    public function testLayoutArgumentsDirectiveIfComplexValues()
    {
        $layout = $this->_getLayoutModel('arguments_complex_values.xml');

        $this->assertEquals(array('parameters' => array('first' => '1', 'second' => '2')),
            $layout->getBlock('block_with_args_complex_values')->getOne());

        $this->assertEquals('two', $layout->getBlock('block_with_args_complex_values')->getTwo());

        $this->assertEquals(array('extra' => array('key1' => 'value1', 'key2' => 'value2')),
            $layout->getBlock('block_with_args_complex_values')->getThree());
    }

    public function testLayoutObjectArgumentsDirective()
    {
        $layout = $this->_getLayoutModel('arguments_object_type.xml');
        $this->assertInstanceOf('Magento\Data\Collection\Db', $layout->getBlock('block_with_object_args')->getOne());
        $this->assertInstanceOf('Magento\Data\Collection\Db',
            $layout->getBlock('block_with_object_args')->getTwo()
        );
        $this->assertEquals(3, $layout->getBlock('block_with_object_args')->getThree());
    }

    public function testLayoutUrlArgumentsDirective()
    {
        $layout = $this->_getLayoutModel('arguments_url_type.xml');
        $this->assertContains('customer/account/login', $layout->getBlock('block_with_url_args')->getOne());
        $this->assertContains('customer/account/logout', $layout->getBlock('block_with_url_args')->getTwo());
        $this->assertContains('customer_id/3', $layout->getBlock('block_with_url_args')->getTwo());
    }

    public function testLayoutObjectArgumentUpdatersDirective()
    {
        $layout = $this->_getLayoutModel('arguments_object_type_updaters.xml');

        $expectedObjectData = array(
            0 => 'updater call',
            1 => 'updater call',
            2 => 'updater call',
        );

        $expectedSimpleData = 2;

        $dataSource = $layout->getBlock('block_with_object_updater_args')->getOne();
        $this->assertInstanceOf('Magento\Data\Collection', $dataSource);
        $this->assertEquals($expectedObjectData, $dataSource->getUpdaterCall());
        $this->assertEquals($expectedSimpleData, $layout->getBlock('block_with_object_updater_args')->getTwo());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testMoveSameAlias()
    {
        $layout = $this->_getLayoutModel('move_the_same_alias.xml');
        $this->assertEquals('container1', $layout->getParentName('no_name3'));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testMoveNewAlias()
    {
        $layout = $this->_getLayoutModel('move_new_alias.xml');
        $this->assertEquals('new_alias', $layout->getElementAlias('no_name3'));
    }

    public function testActionAnonymousParentBlock()
    {
        $layout = $this->_getLayoutModel('action_for_anonymous_parent_block.xml');
        $this->assertEquals('schedule_block', $layout->getParentName('test.block.insert'));
        $this->assertEquals('schedule_block_1', $layout->getParentName('test.block.append'));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testRemove()
    {
        $layout = $this->_getLayoutModel('remove.xml');
        $this->assertFalse($layout->getBlock('no_name2'));
        $this->assertFalse($layout->getBlock('child_block1'));
        $this->assertTrue($layout->isBlock('child_block2'));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testMove()
    {
        $layout = $this->_getLayoutModel('move.xml');
        $this->assertEquals('container2', $layout->getParentName('container1'));
        $this->assertEquals('container1', $layout->getParentName('no.name2'));
        $this->assertEquals('block_container', $layout->getParentName('no_name3'));

        // verify `after` attribute
        $this->assertEquals('block_container', $layout->getParentName('no_name'));
        $childrenOrderArray = array_keys($layout->getChildBlocks($layout->getParentName('no_name')));
        $positionAfter = array_search('child_block1', $childrenOrderArray);
        $positionToVerify = array_search('no_name', $childrenOrderArray);
        $this->assertEquals($positionAfter, --$positionToVerify);

        // verify `before` attribute
        $this->assertEquals('block_container', $layout->getParentName('no_name4'));
        $childrenOrderArray = array_keys($layout->getChildBlocks($layout->getParentName('no_name4')));
        $positionBefore = array_search('child_block2', $childrenOrderArray);
        $positionToVerify = array_search('no_name4', $childrenOrderArray);
        $this->assertEquals($positionBefore, ++$positionToVerify);
    }

    /**
     * @expectedException \Magento\Exception
     */
    public function testMoveBroken()
    {
        $this->_getLayoutModel('move_broken.xml');
    }

    /**
     * @expectedException \Magento\Exception
     */
    public function testMoveAliasBroken()
    {
        $this->_getLayoutModel('move_alias_broken.xml');
    }

    /**
     * @expectedException \Magento\Exception
     */
    public function testRemoveBroken()
    {
        $this->_getLayoutModel('remove_broken.xml');
    }

    /**
     * @param string $case
     * @param string $expectedResult
     * @dataProvider sortSpecialCasesDataProvider
     * @magentoAppIsolation enabled
     */
    public function testSortSpecialCases($case, $expectedResult)
    {
        $layout = $this->_getLayoutModel($case);
        $this->assertEquals($expectedResult, $layout->renderElement('root'));
    }

    /**
     * @return array
     */
    public function sortSpecialCasesDataProvider()
    {
        return array(
            'Before element which is after' => array('sort_before_after.xml', '312'),
            'Before element which is previous' => array('sort_before_before.xml', '213'),
            'After element which is after' => array('sort_after_after.xml', '312'),
            'After element which is previous' => array('sort_after_previous.xml', '321'),
        );
    }

    /**
     * Prepare a layout model with pre-loaded fixture of an update XML
     *
     * @param string $fixtureFile
     * @return \Magento\Core\Model\Layout
     */
    protected function _getLayoutModel($fixtureFile)
    {
        /** @var $layout \Magento\Core\Model\Layout */
        $layout = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get('Magento\Core\Model\Layout');
        /** @var $xml \Magento\Core\Model\Layout\Element */
        $xml = simplexml_load_file(
            __DIR__ . "/_files/layout_directives_test/{$fixtureFile}",
            'Magento\Core\Model\Layout\Element'
        );
        $layout->loadString($xml->asXml());
        $layout->generateElements();
        return $layout;
    }

    /**
     * @magentoConfigFixture current_store true_options 1
     */
    public function testIfConfigForBlock()
    {
        $layout = $this->_getLayoutModel('ifconfig.xml');
        $this->assertFalse($layout->getBlock('block1'));
        $this->assertInstanceOf('Magento\Core\Block', $layout->getBlock('block2'));
        $this->assertInstanceOf('Magento\Core\Block', $layout->getBlock('block3'));
        $this->assertFalse($layout->getBlock('block4'));
    }
}

