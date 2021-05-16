<?php

namespace Codeception\Module;

class CommerceHelper extends \Codeception\Module {

  protected $config = [];

  protected \Codeception\Module\WebDriver $webDriverModule;

  protected \Codeception\Module\Db $dbModule;

  protected \Codeception\Module\AcceptanceHelper $acceptanceHelperModule;

  protected \Codeception\Module\DrupalHelper $drupalHelperModule;

  /**
   * {@inheritDoc}
   */
  public function _initialize(): void {
    $this->webDriverModule = $this->getModule('WebDriver');
    $this->dbModule = $this->getModule('Db');
    $this->acceptanceHelperModule = $this->getModule('AcceptanceHelper');
    $this->drupalHelperModule = $this->getModule('DrupalHelper');
  }

  /**
   * Return last added product id.
   */
  public function grabLastAddedProductId(string $product_type = null): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(product_id)
      FROM commerce_product
      " . ($product_type ? "WHERE type = '$product_type'" : "") . "
    ")->fetchColumn();
  }

  /**
   * Delete product.
   */
  public function deleteProduct(int $product_id, bool $use_browser = false): void {
    if ($use_browser) {
      $this->drupalHelperModule->rememberCurrentSession();
      $this->drupalHelperModule->loginAsAdmin();
      $this->drupalHelperModule->amOnDrupalPage('/product/' . $product_id . '/delete');
      $this->webDriverModule->click('.form-submit');
      $this->drupalHelperModule->dontSeeDrupalErrors();
      $this->drupalHelperModule->restoreRememberedSession();
    }
    else {
      $this->drupalHelperModule->runDrush("entity-delete commerce_product $product_id");
    }
  }

  /**
   * Delete products.
   */
  public function deleteProducts(array $products_ids, bool $use_browser = false): void {
    if ($use_browser) {
      foreach ($products_ids as $product_id) {
        $this->deleteProduct($product_id, $use_browser);
      }
    }
    else {
      $this->drupalHelperModule->runDrush('entity-delete commerce_product ' . implode(',', $products_ids));
    }
  }

  /**
   * Delete all products.
   */
  public function deleteAllProducts(): void {
    $this->drupalHelperModule->runDrush('entity-delete commerce_product');
  }

  /**
   * Publish/unpublish product using sql query.
   */
  public function changeProductPublishStatus($product_id, bool $publish_status = true): void {
    if (is_array($product_id)) {
      $product_id = implode(',', $product_id);
    }

    $this->acceptanceHelperModule->sqlQuery("
      UPDATE commerce_product_field_data
      SET status = 1
      WHERE product_id IN ($product_id)
    ")->execute();

    $this->drupalHelperModule->clearCacheTable('entity');
  }

  /**
   * Return product path alias.
   */
  public function grabProductAlias(int $product_id): string {
    return $this->drupalHelperModule->grabPathAlias('/product/' . $product_id);
  }

  /**
   * Open product page.
   */
  public function amOnProductPage(int $product_id): void {
    $this->drupalHelperModule->amOnDrupalPage($this->grabProductAlias($product_id));
  }

  /**
   * Clear cart.
   */
  public function clearCart(): void {
    $this->drupalHelperModule->amOnDrupalPage('/cart');
    while ($this->acceptanceHelperModule->grabNumberOfElement('.delete-order-item')) {
      $this->webDriverModule->click('.delete-order-item');
    }
  }

}
