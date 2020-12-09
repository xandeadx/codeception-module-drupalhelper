<?php

namespace Codeception\Module;

class AcceptanceHelper extends \Codeception\Module {

  protected $config = [
    'page_title_selector' => '.page-title',
    'breadcrumb_item_selector' => '.breadcrumb__item',
  ];

  protected \Codeception\Module\WebDriver $webDriverModule;

  protected \Codeception\Module\Db $dbModule;

  /**
   * {@inheritDoc}
   */
  public function _initialize(): void {
    $this->webDriverModule = $this->getModule('WebDriver');
    $this->dbModule = $this->getModule('Db');
  }

  /**
   * Sql query.
   *
   * @return \PDOStatement|false
   */
  public function sqlQuery(string $query) {
    return $this->dbModule->_getDbh()->query($query);
  }

  /**
   * See page title.
   */
  public function seePageTitle(string $page_title): void {
    $this->webDriverModule->see($page_title, $this->config['page_title_selector']);
  }

  /**
   * See list.
   */
  public function seeList(array $items, string $item_selector): void {
    foreach ($items as $key => $item) {
      $this->webDriverModule->see($item, $item_selector . ':nth-child(' . ($key + 1) . ')');
    }
  }

  /**
   * See breadcrumb.
   */
  public function seeBreadcrumb(array $items): void {
    $this->seeList($items, $this->config['breadcrumb_item_selector']);
  }

  /**
   * See element attribute exists or see attribute value.
   */
  public function seeElementAttribute(string $element_selector, string $attribute_name, string $attribute_value = NULL): void {
    $this->webDriverModule->seeElementInDOM($element_selector);
    $this->webDriverModule->seeElementInDOM($element_selector . '[' . $attribute_name . ']');
    if ($attribute_value !== NULL) {
      $element_attribute_value = $this->webDriverModule->grabAttributeFrom($element_selector, $attribute_name);
      $this->assertEquals($attribute_value, $element_attribute_value);
    }
  }

  /**
   * See element attribute not exists.
   */
  public function dontSeeElementAttribute(string $element_selector, string $attribute_name): void {
    $this->webDriverModule->seeElementInDOM($element_selector);
    $this->webDriverModule->dontSeeElementInDOM($element_selector . '[' . $attribute_name . ']');
  }

  /**
   * See field by name.
   */
  public function seeField(string $name) {
    $this->webDriverModule->seeElement('[name="' . $name . '"]');
  }

  /**
   * See in DOM field by name.
   */
  public function seeFieldInDom(string $name) {
    $this->webDriverModule->seeElementInDOM('[name="' . $name . '"]');
  }

  /**
   * Return max database value.
   */
  public function grabMaxDatabaseValue(string $table, string $column, string $where = null): string {
    $query = "SELECT MAX($column) FROM $table";

    if ($where) {
      $query .= " WHERE $where";
    }

    return $this->sqlQuery($query)->fetchColumn();
  }

  /**
   * Fill checkbox.
   */
  public function fillCheckbox(string $checkbox, bool $enabled): void {
    if ($enabled) {
      $this->webDriverModule->checkOption($checkbox);
    }
    else {
      $this->webDriverModule->uncheckOption($checkbox);
    }
  }

  /**
   * Click using javascript.
   */
  public function jsClick(string $selector): void {
    $this->webDriverModule->executeJS("document.querySelector('$selector').click();");
  }

}
