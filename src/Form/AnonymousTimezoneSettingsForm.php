<?php

namespace Drupal\anonymous_timezone\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GeoIp2\Database\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure module settings.
 */
class AnonymousTimezoneSettingsForm extends ConfigFormBase {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('config.factory'),
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

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
    $validators = [
      'file_validate_extensions' => ['mmdb'],
    ];
    $form['geodb_file'] = [
      '#type' => 'managed_file',
      '#name' => 'geodb_file',
      '#title' => t('GeoDB File'),
      '#size' => 20,
      '#description' => t('If no access to the server / codebase, you might also upload the mmdb file here.'),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://anonymous_timezone/',
    ];
    $exclude_paths = $config->get('exclude_paths');
    if (empty($exclude_paths)) {
      $exclude_paths = [];
    }
    $form['exclude_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude paths - where anonymous timezone detection should be disabled'),
      '#description' => $this->t('That way page cache can be restored selectively where no timezone-dependent information is rendered. Put one path per line.'),
      '#default_value' => join("\n", $exclude_paths),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $geodb_file = $form_state->getValue('geodb_file');
    $geodb_path = $form_state->getValue('geodb');
    if (!file_exists($geodb_path) && empty($geodb_file)) {
      $form_state->setError($form['geodb'], $this->t('No GeoDB file available. Either specify a path directly or upload it via the file field.'));
      return;
    }

    if (!empty($geodb_file)) {
      [$fid] = $geodb_file;
      /** @var \Drupal\file\FileStorageInterface $file_storage */
      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $uploaded_file = $file_storage->load($fid);
      $geodb_path = $uploaded_file->getFileUri();
    }

    try {
      $reader = new Reader($geodb_path);
      $reader->city($_SERVER['REMOTE_ADDR']);
    }
    catch (\Exception $e) {
      if (strstr($e->getMessage(), 'is not in the database') === FALSE) {
        $form_state->setError($form['geodb'], $e->getMessage());
      }
    }

    // If the validation seems to be okay, use the uploaded path.
    if (!empty($geodb_file)) {
      $form_state->setValue('geodb', $geodb_path);
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $exclude_paths = preg_split("/\r\n|\n|\r/", $form_state->getValue('exclude_paths'));
    foreach ($exclude_paths as $key => $exclude_path) {
      if (empty($exclude_path)) {
        unset($exclude_paths[$key]);
      }
    }
    $config = $this->config('anonymous_timezone.settings');
    $config->set('geodb', $this->fileSystem->realpath($form_state->getValue('geodb')));
    $config->set('exclude_paths', $exclude_paths);
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
