<?php

namespace Drupal\acquia_cms_component\Form;

use Drupal\component\ComponentDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
    $form['assets_js'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('JS'),
      '#description' => $this->t('External js libraries, in case of multiple, enter one value per line.'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['!value' => ''],
        ],
      ],
    ];
    $form['assets_css'] = [
      '#type' => 'textarea',
      '#rows' => 2,
      '#title' => $this->t('CSS'),
      '#description' => $this->t('External css libraries, in case of multiple, enter one value per line.'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['!value' => ''],
        ],
      ],
    ];
    $libraries_components = $this->calcLibraries();
    if (!empty($libraries_components)) {
      $form['dependencies'] = $this->getLibraryDependencies($libraries_components);
    }
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
   * Get library type components.
   */
  private function calcLibraries(): array {
    $libraries = [];
    $component_list = $this->componentDiscovery->getComponents();
    foreach ($component_list as $components) {
      foreach ($components as $name => $component) {
        if ($component['type'] == 'library') {
          if (!empty(_component_build_library($component['js'], $component['subpath']))) {
            $libraries[$name] = $name;
          }
        }
      }
    }
    return $libraries;
  }

  /**
   * Generate option for existing dependencies as libraries.
   */
  private function getLibraryDependencies(array $libraries_components): array {
    return [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $libraries_components,
      '#title' => $this->t('Dependencies'),
      '#description' => $this->t('In case of multiple, select multiple.'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['!value' => ''],
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
    $type = $form_state->getValue('type');
    $assets_css = $form_state->getValue('assets_css');
    $assets_js = $form_state->getValue('assets_js');
    $dependencies = $form_state->getValue('dependencies');
    if ($type && empty($assets_css) && empty($assets_js) && empty($dependencies)) {
      $form_state->setErrorByName('assets_css', $this->t('Missing css library'));
      $form_state->setErrorByName('assets_js', $this->t('Missing js library.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $assets_css = $assets_js = '';
    $component_type = $form_state->getValue('type');
    $component_name = $form_state->getValue('label');
    $component_machine_name = $form_state->getValue('id');
    $component_desc = $form_state->getValue('description');
    $component_dependencies = $form_state->getValue('dependencies');

    if ($component_type) {
      $assets_css = $form_state->getValue('assets_css');
      $assets_js = $form_state->getValue('assets_js');
    }

    $component_module_path = $this->moduleHandler->getModule('acquia_cms_component')->getPath();
    $component_module_path .= '/components/' . $component_machine_name;
    $component_file_name = $component_module_path . '/' . $component_machine_name . '.component.yml';
    $this->fileSystem->prepareDirectory($component_module_path, FileSystemInterface::CREATE_DIRECTORY);

    $component_content = [
      'name' => $component_name,
      'description' => $component_desc,
      'type' => $component_type,
    ];
    // Add js library assets.
    if ($assets_js) {
      $js_lib = explode(PHP_EOL, $assets_js);
      foreach ($js_lib as $lib) {
        $lib = trim(str_replace('\r', '', $lib));
        $component_content['js'][$lib] = [
          'type' => 'external',
          'minified' => TRUE,
          'crossorigin' => 'anonymous',
        ];
      }
    }
    // Add css library assets.
    if ($assets_css) {
      $css_lib = explode(PHP_EOL, $assets_css);
      foreach ($css_lib as $lib) {
        $lib = trim(str_replace('\r', '', $lib));
        $component_content['css'][$lib] = [
          'type' => 'external',
          'minified' => TRUE,
          'crossorigin' => 'anonymous',
        ];
      }
    }

    if ($component_dependencies) {
      $component_content['dependencies'] = [$component_dependencies];
    }
    $component_yml_content = Yaml::dump($component_content, 2, 2);
    file_put_contents($component_file_name, $component_yml_content);
    $this->messenger()->addStatus($this->t('Component <strong>[:name]</strong> created', [':name' => $component_name]));
    $url = Url::fromRoute('component.admin_form');
    $form_state->setRedirectUrl($url);
  }

}
