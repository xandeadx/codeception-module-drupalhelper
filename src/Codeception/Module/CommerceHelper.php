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
  public function grabLastAddedProductId(string $product_type = null): ?int {
    $product_id = $this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(product_id)
      FROM commerce_product
      " . ($product_type ? "WHERE type = '$product_type'" : "") . "
    ")->fetchColumn();

    return $product_id ? (int)$product_id : null;
  }

  /**
   * Return last added product id.
   */
  public function grabLastAddedVariationId(string $variation_type = null): int {
    return (int)$this->acceptanceHelperModule->sqlQuery("
      SELECT MAX(variation_id)
      FROM commerce_product_variation
      " . ($variation_type ? "WHERE type = '$variation_type'" : "") . "
    ")->fetchColumn();
  }

  /**
   * Delete products.
   */
  public function deleteProducts(array|int $products_ids, bool $use_browser = false): void {
    if (!is_array($products_ids)) {
      $products_ids = [$products_ids];
    }

    if ($use_browser) {
      foreach ($products_ids as $product_id) {
        $this->drupalHelperModule->rememberCurrentSession();
        $this->drupalHelperModule->loginAsAdmin();
        $this->drupalHelperModule->amOnDrupalPage('/product/' . $product_id . '/delete');
        $this->webDriverModule->click('.form-submit');
        $this->drupalHelperModule->dontSeeDrupalErrors();
        $this->drupalHelperModule->restoreRememberedSession();
      }
    }
    else {
      $this->drupalHelperModule->deleteEntities('commerce_product', $products_ids);
    }
  }

  /**
   * Delete all products.
   */
  public function deleteAllProducts(): void {
    $this->drupalHelperModule->deleteEntities('commerce_product');
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
   * Return product id by title.
   */
  public function grabProductIdByTitle(string $product_title): ?int {
    $product_id = $this->acceptanceHelperModule->sqlQuery("
      SELECT p.product_id
      FROM commerce_product_field_data p
      WHERE p.title = '$product_title'
    ")->fetchColumn();

    return $product_id ? (int)$product_id : null;
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
    while ($this->acceptanceHelperModule->grabNumberOfElements('.delete-order-item')) {
      $this->webDriverModule->click('.delete-order-item');
    }
  }

}
