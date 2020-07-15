<?php

namespace Codeception\Module;

use Codeception\Module as CodeceptionModule;

class DrupalHelper extends CodeceptionModule {

  public function login($username, $password) {
    $this->amOnPage('/user');
    if ($this->tryToSeeElement('.user-login-form')) {
      $this->fillField('#edit-name', $username);
      $this->fillField('#edit-pass', $password);
      $this->click('#edit-submit');
    }
  }

}
