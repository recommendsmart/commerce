<?php

namespace Drupal\Tests\commerce_pricelist\Functional;

use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\CsvFileObject;
use Drupal\commerce_pricelist\Entity\PriceListItem;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Core\Url;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Tests the price list item UI.
 *
 * @group commerce_pricelist
 */
class PriceListItemTest extends CommerceBrowserTestBase {

  /**
   * A test price list.
   *
   * @var \Drupal\commerce_pricelist\Entity\PriceListInterface
   */
  protected $priceList;

  /**
   * A test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $firstVariation;

  /**
   * A test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $secondVariation;

  /**
   * The price list item collection uri.
   *
   * @var string
   */
  protected $priceListItemCollectionUri;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_product',
    'commerce_pricelist',
    'commerce_pricelist_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_pricelist',
      'administer commerce_product',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Turn off title generation to allow explicit values to be used.
    $variation_type = ProductVariationType::load('default');
    $variation_type->setGenerateTitle(FALSE);
    $variation_type->save();

    $this->priceList = $this->createEntity('commerce_pricelist', [
      'type' => 'commerce_product_variation',
      'name' => $this->randomMachineName(8),
      'start_date' => '2018-07-07',
    ]);
    $this->firstVariation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'RED-SHIRT',
      'title' => 'Red shirt',
      'price' => new Price('12.00', 'USD'),
    ]);
    $this->secondVariation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'BLUE-SHIRT',
      'title' => 'Blue shirt',
      'price' => new Price('11.00', 'USD'),
    ]);
    $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$this->firstVariation, $this->secondVariation],
    ]);
    $this->priceListItemCollectionUri = Url::fromRoute('entity.commerce_pricelist_item.collection', [
      'commerce_pricelist' => $this->priceList->id(),
    ])->toString();
    $this->extensionPathResolver = \Drupal::service('extension.path.resolver');
  }

  /**
   * Tests adding a price list item.
   */
  public function testAdd() {
    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Add price');

    $this->submitForm([
      'purchasable_entity[0][target_id]' => 'Red shirt (1)',
      'quantity[0][value]' => '10',
      'price[0][number]' => 50,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Red shirt: $50.00 price.');

    $price_list_item = PriceListItem::load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('10', $price_list_item->getQuantity());
    $this->assertEquals(new Price('50', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests editing a price list item.
   */
  public function testEdit() {
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('50', 'USD'),
    ]);
    $this->drupalGet($price_list_item->toUrl('edit-form'));
    $this->submitForm([
      'purchasable_entity[0][target_id]' => 'Blue shirt (2)',
      'quantity[0][value]' => '9',
      'price[0][number]' => 40,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Blue shirt: $40.00 price.');

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist_item')->resetCache([$price_list_item->id()]);
    $price_list_item = PriceListItem::load(1);
    $this->assertEquals($this->secondVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('9', $price_list_item->getQuantity());
    $this->assertEquals(new Price('40', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests duplicating a price list item.
   */
  public function testDuplicate() {
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('50', 'USD'),
    ]);
    $this->drupalGet($price_list_item->toUrl('duplicate-form'));
    $this->assertSession()->pageTextContains('Duplicate Red shirt: $50.00');
    $this->submitForm([
      'quantity[0][value]' => '20',
      'price[0][number]' => 25,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Red shirt: $25.00 price.');

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist_item')->resetCache([$price_list_item->id()]);
    // Confirm that the original price list item is unchanged.
    $price_list_item = PriceListItem::load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('10', $price_list_item->getQuantity());
    $this->assertEquals(new Price('50', 'USD'), $price_list_item->getPrice());

    // Confirm that the new price list item has the expected data.
    $price_list_item = PriceListItem::load(2);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('20', $price_list_item->getQuantity());
    $this->assertEquals(new Price('25', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests deleting a price list item.
   */
  public function testDelete() {
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('50', 'USD'),
    ]);
    $this->drupalGet($price_list_item->toUrl('delete-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], t('Delete'));

    \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist_item')->resetCache([$price_list_item->id()]);
    $price_list_item_exists = (bool) PriceListItem::load($price_list_item->id());
    $this->assertFalse($price_list_item_exists);
  }

  /**
   * Tests importing price list items and deleting existing price list items.
   */
  public function testImportPriceListItemsWithDelete() {
    // A price list item to be deleted.
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('666', 'USD'),
    ]);

    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Import prices');

    $filepath = $this->extensionPathResolver->getPath('module', 'commerce_pricelist_test') . '/files/prices.csv';
    $this->getSession()->getPage()->attachFileToField('files[csv]', $filepath);
    $this->submitForm([
      'mapping[quantity_column]' => 'qty',
      'mapping[list_price_column]' => 'msrp',
      'mapping[currency_column]' => 'currency',
      'delete_existing' => TRUE,
    ], 'Import prices');
    $this->assertSession()->pageTextContains('Imported 2 prices.');
    $this->assertSession()->pageTextContains('Skipped 2 prices during import.');
    $this->assertSession()->pageTextContains('Red shirt');
    $this->assertSession()->pageTextContains('Blue shirt');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $price_list_item_storage */
    $price_list_item_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_pricelist_item');
    // Confirm that the existing price list item was deleted.
    $price_list_item_storage->resetCache([$price_list_item->id()]);
    $price_list_item_exists = (bool) PriceListItem::load($price_list_item->id());
    $this->assertFalse($price_list_item_exists);

    // Confirm that two new price list items have been created.
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface[] $price_list_items */
    $price_list_items = $price_list_item_storage->loadMultiple();
    $this->assertCount(2, $price_list_items);
    $first_price_list_item = reset($price_list_items);
    $this->assertEquals($this->priceList->id(), $first_price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $first_price_list_item->getPurchasableEntityId());
    $this->assertEquals('1', $first_price_list_item->getQuantity());
    $this->assertEquals(new Price('50', 'USD'), $first_price_list_item->getListPrice());
    $this->assertEquals(new Price('40', 'USD'), $first_price_list_item->getPrice());
    $this->assertTrue($first_price_list_item->isEnabled());

    $second_price_list_item = end($price_list_items);
    $this->assertEquals($this->priceList->id(), $second_price_list_item->getPriceListId());
    $this->assertEquals($this->secondVariation->id(), $second_price_list_item->getPurchasableEntityId());
    $this->assertEquals('3', $second_price_list_item->getQuantity());
    $this->assertEquals(new Price('99.99', 'USD'), $second_price_list_item->getListPrice());
    $this->assertEquals(new Price('89.99', 'USD'), $second_price_list_item->getPrice());
    $this->assertTrue($second_price_list_item->isEnabled());
  }

  /**
   * Tests importing price list items and updating existing price list items.
   */
  public function testImportPriceListItemsWithUpdate() {
    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Import prices');

    $test_module_path = $this->extensionPathResolver->getPath('module', 'commerce_pricelist_test');
    $filepath = $test_module_path . '/files/prices.csv';
    $this->getSession()->getPage()->attachFileToField('files[csv]', $filepath);
    $this->submitForm([
      'mapping[quantity_column]' => 'qty',
      'mapping[list_price_column]' => 'msrp',
      'mapping[currency_column]' => 'currency',
      'delete_existing' => FALSE,
    ], 'Import prices');
    $this->assertSession()->pageTextContains('Imported 2 prices.');
    $this->assertSession()->pageTextContains('Skipped 2 prices during import.');

    $price_list_item_storage = $this->container->get('entity_type.manager')->getStorage('commerce_pricelist_item');
    // Confirm that two new price list items have been created.
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface[] $price_list_items */
    $price_list_items = $price_list_item_storage->loadMultiple();
    $this->assertCount(2, $price_list_items);

    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Import prices');

    $filepath = $test_module_path . '/files/prices_update.csv';
    $this->getSession()->getPage()->attachFileToField('files[csv]', $filepath);
    $this->submitForm([
      'mapping[quantity_column]' => 'qty',
      'mapping[list_price_column]' => 'msrp',
      'mapping[currency_column]' => 'currency',
      'delete_existing' => FALSE,
    ], 'Import prices');
    $this->assertSession()->pageTextContains('Imported 1 price.');
    $this->assertSession()->pageTextContains('Updated 2 prices.');
    $this->assertSession()->pageTextContains('Skipped 1 price during import.');

    $price_list_item_storage->resetCache();
    // Confirm that quantities 1 and 3 were updated, and quantity 5 was created.
    $price_list_item_ids = $price_list_item_storage->getQuery()
      ->condition('purchasable_entity', $this->firstVariation->id())
      ->accessCheck(FALSE)
      ->condition('quantity', 1)
      ->execute();
    $this->assertCount(1, $price_list_item_ids);
    /** @var \Drupal\commerce_pricelist\Entity\PriceListItemInterface $price_list_item */
    $price_list_item = $price_list_item_storage->load(reset($price_list_item_ids));
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('1', $price_list_item->getQuantity());
    $this->assertEquals(new Price('60.00', 'USD'), $price_list_item->getListPrice());
    $this->assertEquals(new Price('50.00', 'USD'), $price_list_item->getPrice());
    $this->assertTrue($price_list_item->isEnabled());

    $price_list_item_ids = $price_list_item_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('purchasable_entity', $this->secondVariation->id())
      ->condition('quantity', 3)
      ->execute();
    $this->assertCount(1, $price_list_item_ids);
    $price_list_item = $price_list_item_storage->load(reset($price_list_item_ids));
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->secondVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('3', $price_list_item->getQuantity());
    $this->assertEquals(new Price('89.99', 'USD'), $price_list_item->getListPrice());
    $this->assertEquals(new Price('79.99', 'USD'), $price_list_item->getPrice());
    $this->assertTrue($price_list_item->isEnabled());

    $price_list_item_ids = $price_list_item_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('purchasable_entity', $this->secondVariation->id())
      ->condition('quantity', 5)
      ->execute();
    $this->assertCount(1, $price_list_item_ids);
    $price_list_item = $price_list_item_storage->load(reset($price_list_item_ids));
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->secondVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('5', $price_list_item->getQuantity());
    $this->assertEquals(new Price('89.99', 'USD'), $price_list_item->getListPrice());
    $this->assertEquals(new Price('79.99', 'USD'), $price_list_item->getPrice());
    $this->assertTrue($price_list_item->isEnabled());
  }

  /**
   * Tests importing price list items with badly formatted prices.
   */
  public function testImportPriceListItemsWithBadPrices() {
    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Import prices');
    $this->submitForm([
      'files[csv]' => __DIR__ . '/../../fixtures/price_list_invalid_prices.csv',
      'mapping[purchasable_entity_column_type]' => 'sku',
      'mapping[purchasable_entity_column]' => 'product_variation',
      'mapping[quantity_column]' => 'quantity',
      'mapping[list_price_column]' => 'list_price',
      'mapping[price_column]' => 'price',
      'mapping[currency_column]' => 'currency_code',
      'options[delimiter]' => ',',
      'options[enclosure]' => '"',
    ], 'Import prices');
    $this->assertSession()->pageTextContains('Imported 1 price.');
    // We have a badly formatted price in there as well, which we are expecting
    // to be skipped.
    $this->assertSession()->pageTextContains('Skipped 1 price during import.');
    $price_list_item_storage = $this->container->get('entity_type.manager')->getStorage('commerce_pricelist_item');
    $price_list_item = $price_list_item_storage->load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('1', $price_list_item->getQuantity());
    $this->assertEquals(new Price('4000', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Make sure a file with a bad file ending is imported.
   */
  public function testImportDifferentLineEndings() {
    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Import prices');
    $this->submitForm([
      'files[csv]' => __DIR__ . '/../../fixtures/price_list_mac_line_endings.csv',
      'mapping[purchasable_entity_column_type]' => 'sku',
      'mapping[purchasable_entity_column]' => 'product_variation',
      'mapping[quantity_column]' => 'qty',
      'mapping[list_price_column]' => 'mrsp',
      'mapping[price_column]' => 'price',
      'mapping[currency_column]' => 'currency',
      'options[delimiter]' => ',',
      'options[enclosure]' => '"',
    ], 'Import prices');
    $this->assertSession()->pageTextContains('Imported 2 prices.');
    $price_list_item_storage = $this->container->get('entity_type.manager')->getStorage('commerce_pricelist_item');
    $price_list_item = $price_list_item_storage->load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('1', $price_list_item->getQuantity());
    $this->assertEquals(new Price('40', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests exporting price list items.
   */
  public function testExportPriceListItems() {
    // Create 20 price list items for each of our 2 test variations.
    $expected_rows = [];
    foreach ([$this->firstVariation, $this->secondVariation] as $variation) {
      for ($i = 1; $i <= 20; $i++) {
        $price_list_item = $this->createEntity('commerce_pricelist_item', [
          'type' => 'commerce_product_variation',
          'price_list_id' => $this->priceList->id(),
          'purchasable_entity' => $variation->id(),
          'quantity' => $i,
          'price' => new Price('666', 'USD'),
        ]);
        $expected_rows[] = [
          'purchasable_entity' => $variation->getSku(),
          'quantity' => $price_list_item->getQuantity(),
          'list_price' => '',
          'price' => $price_list_item->getPrice()->getNumber(),
          'currency_code' => 'USD',
        ];
      }
    }
    $this->drupalGet($this->priceListItemCollectionUri);
    $this->clickLink('Export prices');
    $this->submitForm([
      'mapping[quantity_column]' => 'qty',
      'mapping[purchasable_entity_label_column]' => '',
      'mapping[list_price_column]' => 'msrp',
      'mapping[currency_column]' => 'currency',
    ], 'Export prices');
    $this->assertSession()->pageTextContains('Exported 40 prices.');
    $csv = new CsvFileObject('temporary://pricelist-1-prices.csv', TRUE, [
      'product_variation' => 'purchasable_entity',
      'qty' => 'quantity',
      'msrp' => 'list_price',
      'price' => 'price',
      'currency' => 'currency_code',
    ]);
    foreach ($expected_rows as $expected_row) {
      $row = $csv->current();
      $this->assertEquals($expected_row, $row);
      $csv->next();
    }
    $this->assertEquals($csv->count(), 40);
  }

  /**
   * Tests the "Prices" tab and operation for variations.
   */
  public function testPricesTabAndOperation() {
    $this->drupalGet($this->firstVariation->toUrl('collection'));
    $this->assertSession()->linkExists('Prices');
    $route_name = 'view.commerce_product_variation_prices.page';
    $first_variation_prices_uri = Url::fromRoute($route_name, [
      'commerce_product_variation' => $this->firstVariation->id(),
      'commerce_product' => $this->firstVariation->getProduct()->id(),
    ])->toString();
    $this->assertSession()->linkByHrefExists($first_variation_prices_uri);
    $this->drupalGet($this->firstVariation->toUrl('edit-form'));
    $this->assertSession()->linkExists('Prices');
    $this->assertSession()->linkByHrefExists($first_variation_prices_uri);
    $this->clickLink('Prices');
    $this->assertSession()->responseContains('No prices yet.');
    $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => 2,
      'price' => new Price('666', 'USD'),
    ]);
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains($this->priceList->label());
    $this->assertSession()->pageTextContains($this->firstVariation->label());
    $this->assertSession()->pageTextContains('2.00');
    $this->assertSession()->pageTextContains('$666.00');
    $this->assertSession()->linkExists('Edit');
    $this->assertSession()->linkExists('Delete');

    $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => 5,
      'price' => new Price('800', 'USD'),
    ]);
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('5.00');
    $this->assertSession()->pageTextContains('$800.00');

    $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->secondVariation->id(),
      'quantity' => 3,
      'price' => new Price('100', 'USD'),
    ]);
    $this->drupalGet($this->secondVariation->toUrl('collection'));

    $second_variation_prices_uri = Url::fromRoute($route_name, [
      'commerce_product_variation' => $this->secondVariation->id(),
      'commerce_product' => $this->secondVariation->getProduct()->id(),
    ])->toString();
    $this->assertSession()->linkByHrefExists($second_variation_prices_uri);
    $this->drupalGet($second_variation_prices_uri);
    $this->assertSession()->pageTextContains($this->secondVariation->label());
    $this->assertSession()->pageTextContains('3.00');
    $this->assertSession()->pageTextContains('$100.00');

    $elements = $this->xpath('//select[@name="price_list_id"]/option');
    $found_options = [];
    foreach ($elements as $element) {
      $found_options[$element->getValue()] = $element->getText();
    }

    $expected = [
      '' => '- None -',
      1 => $this->priceList->label(),
    ];
    $this->assertEquals($expected, $found_options);
  }

  /**
   * Tests the form for adding a price for a given variation.
   */
  public function testVariationPriceFormAndOperation() {
    $route_parameters = [
      'commerce_product_variation' => $this->firstVariation->id(),
      'commerce_product' => $this->firstVariation->getProduct()->id(),
    ];
    $first_variation_prices_uri = Url::fromRoute('view.commerce_product_variation_prices.page', $route_parameters)->toString();
    $this->drupalGet($first_variation_prices_uri);
    $this->assertSession()->linkExists('Add price');
    $add_price_form_uri = Url::fromRoute('entity.commerce_product_variation.add_price_form', $route_parameters)->toString();
    $this->assertSession()->linkByHrefExists($add_price_form_uri);
    $this->clickLink('Add price');
    $this->assertSession()->fieldNotExists('purchasable_entity');
    $this->assertSession()->fieldExists('price_list_id[0][target_id]');
    $this->submitForm([
      'price_list_id[0][target_id]' => $this->priceList->label() . ' (1)',
      'quantity[0][value]' => '10',
      'price[0][number]' => 50,
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Red shirt: $50.00 price.');
    $price_list_item = PriceListItem::load(1);
    $this->assertEquals($this->priceList->id(), $price_list_item->getPriceListId());
    $this->assertEquals($this->firstVariation->id(), $price_list_item->getPurchasableEntityId());
    $this->assertEquals('10', $price_list_item->getQuantity());
    $this->assertEquals(new Price('50', 'USD'), $price_list_item->getPrice());
  }

  /**
   * Tests disabling a price list item.
   */
  public function testDisable() {
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('50', 'USD'),
    ]);

    $this->assertTrue($price_list_item->isEnabled());
    $this->drupalGet($price_list_item->toUrl('disable-form'));
    $this->assertSession()->pageTextContains(t('Are you sure you want to disable the price list item @label?', ['@label' => $price_list_item->label()]));
    $this->submitForm([], t('Disable'));

    $price_list_item = $this->reloadEntity($price_list_item);
    $this->assertFalse($price_list_item->isEnabled());
  }

  /**
   * Tests enabling a price list item.
   */
  public function testEnable() {
    $price_list_item = $this->createEntity('commerce_pricelist_item', [
      'type' => 'commerce_product_variation',
      'price_list_id' => $this->priceList->id(),
      'purchasable_entity' => $this->firstVariation->id(),
      'quantity' => '10',
      'price' => new Price('50', 'USD'),
      'status' => FALSE,
    ]);

    $this->assertFalse($price_list_item->isEnabled());
    $this->drupalGet($price_list_item->toUrl('enable-form'));
    $this->assertSession()->pageTextContains(t('Are you sure you want to enable the price list item @label?', ['@label' => $price_list_item->label()]));
    $this->submitForm([], t('Enable'));

    $price_list_item = $this->reloadEntity($price_list_item);
    $this->assertTrue($price_list_item->isEnabled());
  }

}
