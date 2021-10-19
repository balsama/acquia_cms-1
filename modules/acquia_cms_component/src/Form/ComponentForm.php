<?php

namespace Drupal\acquia_cms_component\Form;

use Drupal\component\ComponentDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Acquia CMS Component form.
 */
class ComponentForm extends FormBase {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The component discovery service.
   *
   * @var \Drupal\component\ComponentDiscovery
   */
  protected $componentDiscovery;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\component\ComponentDiscovery $component_discovery
   *   The component discovery service.
   */
  public function __construct(
    FileSystemInterface $file_system,
    ModuleHandlerInterface $module_handler,
    ComponentDiscovery $component_discovery
  ) {
    $this->fileSystem = $file_system;
    $this->moduleHandler = $module_handler;
    $this->componentDiscovery = $component_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('module_handler'),
      $container->get('component.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_cms_component_component';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      'label' => [
        '#type' => 'textfield',
        '#title' => 'Name',
        '#required' => TRUE,
      ],
      'id' => [
        '#type' => 'machine_name',
        'label' => 'component name',
        '#maxlength' => 64,
        '#description' => $this->t('A unique name for this item. It must only contain lowercase letters, numbers, and underscores.'),
        '#machine_name' => [
          'exists' => [
            $this,
            'componentExists',
          ],
          'source' => [
            'name',
            'label',
          ],
        ],
      ],
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#rows' => 3,
      '#title' => $this->t('Description'),
    ];
    $form['type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Type'),
      '#options' => [
        'block' => $this->t('Block'),
        'library' => $this->t('Library'),
        'plugin' => $this->t('Plugin'),
      ],
    ];
    $form['assets'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Asset libraries'),
      '#options' => [
        'new' => $this->t('Add new libraries'),
        'existing' => $this->t('Use existing libraries'),
      ],
    ];
    $form['assets_new_css'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Add css library'),
      '#description' => $this->t('In case of multiple value, enter one per line., ex: example.js: {}'),
      '#states' => [
        'visible' => [
          ':input[name="assets"]' => ['value' => 'new'],
        ],
      ],
    ];
    $form['assets_new_js'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('Add js library'),
      '#description' => $this->t('In case of multiple value, enter one per line., ex: example.css: {}'),
      '#states' => [
        'visible' => [
          ':input[name="assets"]' => ['value' => 'new'],
        ],
      ],
    ];
    $form['assets_exist_css'] = $this->getExistingJsLibOptions();
    $form['assets_exist_js'] = $this->getExistingCssLibOptions();
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create component'),
    ];

    return $form;
  }

  /**
   * Generate option for existing javascript libraries.
   */
  private function getExistingJsLibOptions(): array {
    return [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => [],
      '#title' => $this->t('Select js library'),
      '#description' => $this->t('In case of multiple value, select multiple.'),
      '#states' => [
        'visible' => [
          ':input[name="assets"]' => ['value' => 'existing'],
        ],
      ],
    ];
  }

  /**
   * Generate option for existing css libraries.
   */
  private function getExistingCssLibOptions(): array {
    return [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => [],
      '#title' => $this->t('Select js library'),
      '#description' => $this->t('In case of multiple value, select multiple.'),
      '#states' => [
        'visible' => [
          ':input[name="assets"]' => ['value' => 'existing'],
        ],
      ],
    ];
  }

  /**
   * Check if component name already exists.
   *
   * @param string $component_name
   *   The component machine name.
   *
   * @return bool
   *   Status of component existence.
   */
  public function componentExists(string $component_name): bool {
    $duplicate = FALSE;
    $component_list = $this->componentDiscovery->getComponents();
    foreach ($component_list as $components) {
      foreach ($components as $name => $component) {
        if ($name == $component_name) {
          $duplicate = TRUE;
          break;
        }
      }
    }
    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $assets = $form_state->getValue('assets');
    $assets_new_css = $form_state->getValue('assets_new_css');
    $assets_new_js = $form_state->getValue('assets_new_js');

    $assets_exist_css = $form_state->getValue('assets_exist_css');
    $assets_exist_js = $form_state->getValue('assets_exist_js');
    if ($assets == 'new' && empty($assets_new_css) && empty($assets_new_js)) {
      $form_state->setErrorByName('assets_new_css', $this->t('Missing css library'));
      $form_state->setErrorByName('assets_new_js', $this->t('Missing js library.'));
    }
    if ($assets == 'existing' && empty($assets_exist_css) && empty($assets_exist_js)) {
      $form_state->setErrorByName('assets_new_css', $this->t('Missing css library'));
      $form_state->setErrorByName('assets_new_js', $this->t('Missing js library.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $component_name = $form_state->getValue('id');
    $component_module_path = $this->moduleHandler->getModule('acquia_cms_component')->getPath();
    $component_module_path .= '/component/' . $component_name;
    $component_file_name = $component_name . '.component.yml';
    $this->fileSystem->prepareDirectory($component_module_path, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($component_file_name, "Hello here:");
    dump($form_state->getValues());
    die;
  }

}
