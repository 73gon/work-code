<?php
/**
 * ApiTrait — Handles all HTTP API communication with the Pedant API.
 *
 * Provides cURL-based API requests, URL resolution, API key and demo mode caching.
 */
trait ApiTrait
  {
  private string $demoURL = "https://api.demo.pedant.ai";
  private string $productiveURL = "https://api.pedant.ai";
  private string $entityURL = "https://entity.api.pedant.ai";

  private ?bool $isDemoCache = null;
  private ?string $apiKeyCache = null;

  /**
   * Makes an API request using cURL.
   *
   * @param string $url The URL to request.
   * @param string $method HTTP method (GET, POST, etc.).
   * @param array|string|null $postFields POST fields for the request.
   * @param array $headers HTTP headers.
   * @return array{response: string, httpCode: int} The response and HTTP code.
   * @throws JobRouterException If the request fails.
   */
  private function makeApiRequest(string $url, string $method = 'GET', array|string|null $postFields = null, array $headers = []): array
    {
    $this->logInfo("API request: $method $url");
    $this->logDebug('API request details', [
      'method' => $method,
      'url' => $url,
      'hasPostFields' => $postFields !== null,
      'headers' => $headers,
    ]);

    $curl = curl_init();
    if ($curl === false) {
      $this->logError('Failed to initialize cURL');
      throw new JobRouterException('Failed to initialize cURL');
      }

    $defaultHeaders = ['X-API-KEY: ' . $this->getApiKey()];
    $allHeaders = array_merge($defaultHeaders, $headers);

    $options = [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $allHeaders,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
    ];

    if ($postFields !== null) {
      $options[CURLOPT_POSTFIELDS] = $postFields;
      }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);

    if ($response === false) {
      $curlError = curl_error($curl);
      $curlErrno = curl_errno($curl);
      curl_close($curl);
      $this->logError('cURL request failed', null, ['url' => $url, 'curl_error' => $curlError, 'curl_errno' => $curlErrno]);
      throw new JobRouterException('cURL request failed: ' . $curlError);
      }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $this->logInfo("API response: $method $url", ['httpCode' => $httpCode]);
    $this->logDebug('API response details', [
      'httpCode' => $httpCode,
      'responseLength' => strlen($response),
      'response' => substr($response, 0, 2000),
    ]);

    if (!in_array($httpCode, [200, 201])) {
      $this->logWarning("API returned non-success HTTP code: $httpCode", ['url' => $url, 'method' => $method]);
      }

    return ['response' => $response, 'httpCode' => $httpCode];
    }

  /**
   * Gets the cached API key or resolves it.
   */
  private function getApiKey(): string
    {
    if ($this->apiKeyCache === null) {
      $this->apiKeyCache = $this->resolveInputParameter('api_key') ?? '';
      $this->logDebug('API key resolved');
      }
    return $this->apiKeyCache;
    }

  /**
   * Gets whether demo mode is enabled (cached).
   */
  private function isDemo(): bool
    {
    if ($this->isDemoCache === null) {
      $this->isDemoCache = $this->resolveInputParameter('demo') == '1';
      $this->logDebug('Demo mode resolved', ['isDemo' => $this->isDemoCache]);
      }
    return $this->isDemoCache;
    }

  /**
   * Gets the base API URL based on demo mode.
   */
  private function getBaseUrl(): string
    {
    return $this->isDemo() ? $this->demoURL : $this->productiveURL;
    }

  /**
   * Gets the entity API URL based on demo mode.
   */
  private function getEntityUrl(): string
    {
    return $this->isDemo() ? $this->demoURL : $this->entityURL;
    }
  }
