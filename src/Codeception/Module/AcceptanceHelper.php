<?php

namespace Codeception\Module;

class AcceptanceHelper extends \Codeception\Module {

  protected $config = [
    'page_title_selector' => '.page-title',
    'breadcrumb_item_selector' => '.breadcrumb__item',
  ];

  /**
   * @var \Codeception\Module\WebDriver
   */
  protected $webdriver;

  /**
   * @var \Codeception\Module\Db
   */
  protected $db;

  /**
   * {@inheritDoc}
   */
  public function _initialize() {
    $this->webdriver = $this->getModule('WebDriver');
    $this->db = $this->getModule('Db');
  }

  /**
   * Sql query.
   */
  public function sqlQuery($query){
    return $this->db->_getDbh()->query($query);
  }

  /**
   * See page title.
   */
  public function seePageTitle($page_title) {
    $this->webdriver->see($page_title, ['css' => $this->config['page_title_selector']]);
  }

  /**
   * See list.
   */
  public function seeList(array $items, $item_selector) {
    foreach ($items as $key => $item) {
      $this->webdriver->see($item, ['css' => $item_selector . ':nth-child(' . ($key + 1) . ')']);
    }
  }

  /**
   * See breadcrumb.
   */
  public function seeBreadcrumb(array $items) {
    $this->seeList($items, $this->config['breadcrumb_item_selector']);
  }

  /**
   * See element attribute value.
   */
  public function seeElementAttribute(string $element_selector, string $attribute_name, string $attribute_value = NULL) {
    $this->webdriver->seeElementInDOM($element_selector);
    $this->webdriver->seeElementInDOM($element_selector . '[' . $attribute_name . ']');
    if ($attribute_value !== NULL) {
      $element_attribute_value = $this->webdriver->grabAttributeFrom($element_selector, $attribute_name);
      $this->assertEquals($attribute_value, $element_attribute_value);
    }
  }

  /**
   * Return max database value.
   *
   * @return string
   */
  public function grabMaxDatabaseValue($table, $column, $where = null) {
    $query = "SELECT MAX($column) FROM $table";

    if ($where) {
      $query .= " WHERE $where";
    }

    return $this->sqlQuery($query)->fetchColumn();
  }

  /**
   * Fill checkbox.
   */
  public function fillCheckbox($checkbox, $state) {
    if ($state) {
      $this->webdriver->checkOption($checkbox);
    }
    else {
      $this->webdriver->uncheckOption($checkbox);
    }
  }

  /**
   * Click using javascript.
   */
  public function jsClick($selector) {
    $this->webdriver->executeJS("document.querySelector('$selector').click();");
  }

}
