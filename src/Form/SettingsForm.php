<?php

namespace Drupal\dadata_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for the DaData integration module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dadata_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dadata_integration_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dadata_integration.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#default_value' => $config->get('api_url') ?: 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest',
      '#required' => TRUE,
      '#description' => $this->t('Base DaData API URL, for example: https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest'),
    ];

    // Initialize working rows in form storage on first build.
    $stored_fields = $form_state->get('dadata_fields');
    if (!is_array($stored_fields)) {
      $stored_fields = $config->get('fields') ?: [];
      $form_state->set('dadata_fields', $stored_fields);
    }
    $fields = $stored_fields;

    $form['fields_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'dadata-settings-wrapper'],
    ];

    $form['fields_wrapper']['fields'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        'field_id' => $this->t('Field ID'),
        'suggest_type' => $this->t('Suggestion type'),
        'bound' => $this->t('Granularity (bound)'),
        'remove' => $this->t('Actions'),
      ],
      '#empty' => $this->t('No fields have been added yet.'),
    ];

    // Existing rows.
    foreach ($fields as $delta => $field) {
      $form['fields_wrapper']['fields'][$delta]['field_id'] = [
        '#type' => 'textfield',
        '#default_value' => $field['field_id'] ?? '',
        '#size' => 30,
        '#placeholder' => 'e.g. edit-city',
      ];

      $form['fields_wrapper']['fields'][$delta]['suggest_type'] = [
        '#type' => 'select',
        '#options' => [
          'address' => $this->t('Address'),
          'fio'     => $this->t('Full name'),
          'email'   => $this->t('Email'),
          'party'   => $this->t('Company'),
        ],
        '#default_value' => $field['suggest_type'] ?? $field['type'] ?? 'address',
      ];

      $form['fields_wrapper']['fields'][$delta]['bound'] = [
        '#type' => 'select',
        '#options' => [
          'address'    => $this->t('Full address'),
          'country'    => $this->t('Country'),
          'region'     => $this->t('Region'),
          'city'       => $this->t('City'),
          'settlement' => $this->t('Settlement'),
          'street'     => $this->t('Street'),
          'house'      => $this->t('House'),
        ],
        '#default_value' => $field['bound'] ?? 'address',
        '#states' => [
          'disabled' => [
            ':input[name="fields[' . $delta . '][suggest_type]"]' => ['!value' => 'address'],
          ],
        ],
      ];

      $form['fields_wrapper']['fields'][$delta]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_' . $delta,
        '#submit' => ['::removeField'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'dadata-settings-wrapper',
        ],
      ];
    }

    // Add field button.
    $form['fields_wrapper']['add_field'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add field'),
      '#submit' => ['::addField'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'dadata-settings-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX submit handler to add a new row.
   */
  public function addField(array &$form, FormStateInterface $form_state) {
    $fields = $form_state->get('dadata_fields') ?: [];
    $fields[] = [
      'field_id'     => '',
      'suggest_type' => 'address',
      'bound'        => 'address',
    ];
    $form_state->set('dadata_fields', $fields);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX submit handler to remove a row.
   */
  public function removeField(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $name = $trigger['#name']; // e.g. remove_1
    if (strpos($name, 'remove_') === 0) {
      $delta = (int) str_replace('remove_', '', $name);
      $fields = $form_state->get('dadata_fields') ?: [];
      if (isset($fields[$delta])) {
        unset($fields[$delta]);
        $fields = array_values($fields);
        $form_state->set('dadata_fields', $fields);
      }
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * AJAX callback to rebuild the fields wrapper.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['fields_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Use actual submitted values from the table.
    $fields = $form_state->getValue('fields');
    if (!is_array($fields)) {
      $fields = [];
    }

    // Clean empty rows and normalize values.
    $fields = array_values(array_filter($fields, function ($field) {
      return is_array($field) && !empty($field['field_id']);
    }));

    $normalized = array_map(function ($f) {
      $type = $f['suggest_type'] ?? $f['type'] ?? 'address';
      $bound = $f['bound'] ?? 'address';
      return [
        'field_id' => $f['field_id'],
        'type'     => in_array($type, ['address','fio','email','party'], TRUE) ? $type : 'address',
        'bound'    => $bound,
      ];
    }, $fields);

    // Persist config.
    $this->config('dadata_integration.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_url', rtrim($form_state->getValue('api_url'), '/'))
      ->set('fields', $normalized)
      ->save();

    // Reset working storage after successful save.
    $form_state->set('dadata_fields', $normalized);

    parent::submitForm($form, $form_state);
  }

}