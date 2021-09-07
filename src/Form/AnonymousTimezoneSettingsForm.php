<?php

namespace Drupal\anonymous_timezone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * Configure GeoDB for the module.
 */
class AnonymousTimezoneSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anonymous_timezone_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);
    // Default settings.
    $config = $this->config('anonymous_timezone.settings');
    $form['geodb'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to GeoDB city database'),
      '#description' => $this->t('Anonymous Timezone needs a timezone database (mmdb) from <a href=":url">MaxMind</a>. Put the downloaded, extracted database in a path outside of the document root and specify the relative or the absolute path above of the mmdb file.', [
        ':url' => Url::fromUri('https://dev.maxmind.com/geoip/geolite2-free-geolocation-data?lang=en')
          ->toString(),
      ]),
      '#default_value' => $config->get('geodb'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $geodb_path = $form_state->getValue('geodb');
    if (!file_exists($geodb_path)) {
      $form_state->setError($form['geodb'], $this->t('The specified path does not exist'));
    }

    try {
      new Reader($geodb_path);
    }
    catch (InvalidDatabaseException $e) {
      $form_state->setError($form['geodb'], $e->getMessage());
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('anonymous_timezone.settings');
    $config->set('geodb', $form_state->getValue('geodb'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'anonymous_timezone.settings',
    ];
  }

}
