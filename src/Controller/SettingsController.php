<?php

namespace Drupal\dadata_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a controller for working with the DaData API.
 */
class SettingsController extends ControllerBase {

  /**
   * Handles autocomplete suggestions from DaData API.
   *
   * @param string $type
   *   Suggestion type (address, fio, email, party).
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The API response with suggestions.
   */
  public function suggest($type, Request $request) {
    $q = (string) $request->query->get('q', '');
    if ($q === '') {
      return new JsonResponse(['suggestions' => []]);
    }

    $config = $this->config('dadata_integration.settings');
    $api_key = $config->get('api_key');

    // Allowed suggestion types.
    $allowed = ['address', 'fio', 'email', 'party'];
    if (!$type || $type === 'undefined' || !in_array($type, $allowed, TRUE)) {
      $type = 'address';
    }

    // Base payload.
    $payload = [
      'query'    => $q,
      'count'    => min((int) $request->query->get('count', 10), 20),
      'language' => $request->query->get('language', 'ru'),
    ];

    // from_bound / to_bound are only valid for "address".
    if ($type === 'address') {
      $bound = $request->query->get('bound');
      if ($bound && $bound !== 'address') {
        $payload['from_bound'] = ['value' => $bound];
        $payload['to_bound']   = ['value' => $bound];
      }
    }

    // Locations (restrict by country, region, etc).
    if ($request->query->has('locations')) {
      $locations = json_decode($request->query->get('locations'), TRUE);
      if (is_array($locations)) {
        $payload['locations'] = $locations;
      }
    }

    // Locations_geo (restrict by radius).
    if ($request->query->has('locations_geo')) {
      $locations_geo = json_decode($request->query->get('locations_geo'), TRUE);
      if (is_array($locations_geo)) {
        $payload['locations_geo'] = $locations_geo;
      }
    }

    // Build request URL.
    $base_url = $config->get('api_url') ?: 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest';
    $url = rtrim($base_url, '/') . '/' . $type;

    try {
      $client = \Drupal::httpClient();
      $response = $client->post($url, [
        'headers' => [
          'Content-Type'  => 'application/json',
          'Accept'        => 'application/json',
          'Authorization' => "Token {$api_key}",
        ],
        'json' => $payload,
        'timeout' => 8,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE) ?: ['suggestions' => []];
      return new JsonResponse($data);

    }
    catch (RequestException $e) {
      // Log error in Drupal watchdog.
      $this->getLogger('dadata_integration')->error('DaData request failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['suggestions' => [], 'error' => 'request_failed'], 502);
    }
  }

}