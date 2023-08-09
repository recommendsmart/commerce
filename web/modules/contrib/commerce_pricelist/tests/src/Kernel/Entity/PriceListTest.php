<?php

namespace Drupal\Tests\commerce_pricelist\Kernel\Entity;

use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\PriceList;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\commerce_pricelist\Kernel\PriceListKernelTestBase;

/**
 * Tests the price list entity.
 *
 * @coversDefaultClass \Drupal\commerce_pricelist\Entity\PriceList
 * @group commerce_pricelist
 */
class PriceListTest extends PriceListKernelTestBase {

  /**
   * @covers ::getName
   * @covers ::setName
   * @covers ::getStores
   * @covers ::setStores
   * @covers ::getStoreIds
   * @covers ::setStoreIds
   * @covers ::getCustomer
   * @covers ::getCustomers
   * @covers ::setCustomer
   * @covers ::setCustomers
   * @covers ::getCustomerId
   * @covers ::setCustomerId
   * @covers ::getCustomerRoles
   * @covers ::setCustomerRoles
   * @covers ::getStartDate
   * @covers ::setStartDate
   * @covers ::getEndDate
   * @covers ::setEndDate
   * @covers ::getWeight
   * @covers ::setWeight
   * @covers ::isEnabled
   * @covers ::setEnabled
   * @covers ::getItemIds
   */
  public function testPriceList() {
    /** @var \Drupal\commerce_pricelist\Entity\PriceList $price_list */
    $price_list = PriceList::create([
      'type' => 'commerce_product_variation',
    ]);

    $price_list->setName('B2B pricing');
    $this->assertEquals('B2B pricing', $price_list->getName());
    $this->assertEquals('B2B pricing', $price_list->label());

    $price_list->setStores([$this->store]);
    $this->assertEquals([$this->store], $price_list->getStores());
    $this->assertEquals([$this->store->id()], $price_list->getStoreIds());
    $price_list->setStores([]);
    $this->assertEquals([], $price_list->getStores());
    $price_list->setStoreIds([$this->store->id()]);
    $this->assertEquals([$this->store], $price_list->getStores());
    $this->assertEquals([$this->store->id()], $price_list->getStoreIds());

    $price_list->setCustomer($this->user);
    $this->assertEquals($this->user, $price_list->getCustomer());
    $this->assertEquals($this->user->id(), $price_list->getCustomerId());
    $price_list->set('customers', NULL);
    $price_list->setCustomerId($this->user->id());
    $this->assertEquals($this->user->id(), $price_list->getCustomerId());
    $this->assertEquals($this->user, $price_list->getCustomer());
    $price_list->setCustomers([$this->user]);
    $this->assertEquals([$this->user], $price_list->getCustomers());

    $price_list->setCustomerRoles(['authenticated']);
    $this->assertEquals(['authenticated'], $price_list->getCustomerRoles());

    $date_pattern = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $time = $this->container->get('datetime.time');
    $default_start_date = date($date_pattern, $time->getRequestTime());
    $this->assertEquals($default_start_date, $price_list->getStartDate()->format($date_pattern));
    $price_list->setStartDate(new DrupalDateTime('2017-01-01 12:12:12'));
    $this->assertEquals('2017-01-01 12:12:12 UTC', $price_list->getStartDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-01 12:12:12 CET', $price_list->getStartDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertNull($price_list->getEndDate());
    $price_list->setEndDate(new DrupalDateTime('2017-01-31 17:15:00'));
    $this->assertEquals('2017-01-31 17:15:00 UTC', $price_list->getEndDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-31 17:15:00 CET', $price_list->getEndDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertTrue($price_list->isEnabled());
    $price_list->setEnabled(FALSE);
    $this->assertFalse($price_list->isEnabled());

    $price_list->setWeight(20);
    $this->assertEquals(20, $price_list->getWeight());

    $price_list->save();
    $this->assertEmpty($price_list->getItemIds());
    $first_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'quantity' => '1',
      'price' => new Price('1', 'USD'),
    ]);
    $first_item->save();
    $second_item = PriceListItem::create([
      'type' => 'commerce_product_variation',
      'price_list_id' => $price_list->id(),
      'quantity' => '10',
      'price' => new Price('5', 'USD'),
    ]);
    $second_item->save();
    $this->assertEquals([$first_item->id(), $second_item->id()], array_values($price_list->getItemIds()));

    $price_list->delete();
    $this->assertNull($this->reloadEntity($first_item));
    $this->assertNull($this->reloadEntity($second_item));
  }

}
