<?php

declare(strict_types=1);

namespace Cagrille\AliExpressBundle\IopSdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class IopClient
{
    public string $gatewayUrl = '';

    public ?int $connectTimeout = null;

    public ?int $readTimeout = null;

    protected string $signMethod = 'sha256';

    /** @var non-empty-string */
    protected string $sdkVersion = 'iop-sdk-php-20220608';

    public string $logLevel = '';

    public function getAppkey(): string
    {
        return $this->appkey;
    }

    /**
     * @throws \Exception
     */
    public function __construct(
        string $url = '',
        private readonly string $appkey = '',
        private readonly string $secretKey = '',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $length = strlen($url);
        if ($length == 0) {
            throw new \Exception('url is empty', 0);
        }
        $this->gatewayUrl = $url;
        $this->logLevel = Constants::$log_level_error;
    }

    protected function generateSign(string $apiName, array $params): string
    {
        ksort($params);

        $stringToBeSigned = '';
        if (str_contains($apiName, '/')) {//rest服务协议
            $stringToBeSigned .= $apiName;
        }
        foreach ($params as $k => $v) {
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);

        return strtoupper($this->hmac_sha256($stringToBeSigned, $this->secretKey));
    }

    private function hmac_sha256(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    /**
     * @param array<string, string> $apiFields
     * @param array<string, string>|null $headerFields
     *
     * @throws \Exception
     */
    public function curl_get(string $url, array $apiFields = [], ?array $headerFields = null): string
    {
        $ch = curl_init();

        foreach ($apiFields as $key => $value) {
            $url .= '&' . "$key=" . urlencode($value);
        }

        assert($url !== '');
        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_FAILONERROR, false);
        curl_setopt($ch, \CURLOPT_HEADER, false);
        curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);

        if ($headerFields) {
            $headers = [];
            foreach ($headerFields as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
            unset($headers);
        }

        if ($this->readTimeout) {
            curl_setopt($ch, \CURLOPT_TIMEOUT, $this->readTimeout);
        }

        if ($this->connectTimeout) {
            curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        curl_setopt($ch, \CURLOPT_USERAGENT, $this->sdkVersion);

        //https ignore ssl check ?
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == 'https') {
            curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, 0);
        }

        $output = curl_exec($ch);

        $errno = curl_errno($ch);

        if ($errno) {
            curl_close($ch);

            throw new \Exception((string) $errno, 0);
        }

        $httpStatusCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (200 !== $httpStatusCode) {
            throw new \Exception((string) $output, $httpStatusCode);
        }

        assert(is_string($output));

        return $output;
    }

    /**
     * @param array<string, string>|null $postFields
     * @param array<string, array{name: string, type: string, content: string}>|null $fileFields
     * @param array<string, string>|null $headerFields
     *
     * @throws \Exception
     */
    public function curl_post(string $url, ?array $postFields = null, ?array $fileFields = null, ?array $headerFields = null): string
    {
        $ch = curl_init();
        assert($url !== '');

        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_FAILONERROR, false);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

        if ($this->readTimeout) {
            curl_setopt($ch, \CURLOPT_TIMEOUT, $this->readTimeout);
        }

        if ($this->connectTimeout) {
            curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        if ($headerFields) {
            $headers = [];
            foreach ($headerFields as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
            unset($headers);
        }

        curl_setopt($ch, \CURLOPT_USERAGENT, $this->sdkVersion);

        //https ignore ssl check ?
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == 'https') {
            curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, 0);
        }

        $delimiter = '-------------' . uniqid();
        $data = '';
        if ($postFields != null) {
            foreach ($postFields as $name => $content) {
                $data .= '--' . $delimiter . "\r\n";
                $data .= 'Content-Disposition: form-data; name="' . $name . '"';
                $data .= "\r\n\r\n" . $content . "\r\n";
            }
            unset($name,$content);
        }

        if ($fileFields != null) {
            foreach ($fileFields as $name => $file) {
                $data .= '--' . $delimiter . "\r\n";
                $data .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file['name'] . "\" \r\n";
                $data .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
                $data .= $file['content'] . "\r\n";
            }
            unset($name,$file);
        }
        $data .= '--' . $delimiter . '--';

        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt(
            $ch,
            \CURLOPT_HTTPHEADER,
            [
                'Content-Type: multipart/form-data; boundary=' . $delimiter,
                'Content-Length: ' . strlen($data),
            ],
        );

        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        unset($data);

        $errno = curl_errno($ch);
        if ($errno) {
            curl_close($ch);

            throw new \Exception((string) $errno, 0);
        }

        $httpStatusCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (200 !== $httpStatusCode) {
            throw new \Exception(is_string($response) ? $response : '', $httpStatusCode);
        }

        assert(is_string($response));

        return $response;
    }

    public function execute(IopRequest $request, ?string $accessToken = null): IopResponse
    {
        $sysParams['app_key'] = $this->appkey;
        $sysParams['sign_method'] = $this->signMethod;
        $sysParams['timestamp'] = $this->msectime();
        $sysParams['method'] = $request->apiName;

        $sysParams['partner_id'] = $this->sdkVersion;
        $sysParams['simplify'] = $request->simplify;
        $sysParams['format'] = $request->format;

        if (null != $accessToken) {
            $sysParams['session'] = $accessToken;
        }

        $apiParams = $request->udfParams;

        $requestUrl = $this->gatewayUrl;

        if ($this->endWith($requestUrl, '/')) {
            $requestUrl = substr($requestUrl, 0, -1);
        }

        $requestUrl .= $request->apiName;
        $requestUrl .= '?';

        if ($this->logLevel == Constants::$log_level_debug) {
            $sysParams['debug'] = 'true';
        }
        $sysParams['sign'] = $this->generateSign($request->apiName, array_merge($apiParams, $sysParams));

        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . '&';
        }

        $requestUrl = substr($requestUrl, 0, -1);

        $resp = '';

        try {
            if ($request->httpMethod == 'POST') {
                $resp = $this->curl_post($requestUrl, $apiParams, $request->fileParams, $request->headerParams);
            } else {
                $resp = $this->curl_get($requestUrl, $apiParams, $request->headerParams);
            }
        } catch (\Exception $e) {
            $this->logger->debug('[AliExpress IOP] HTTP_ERROR_', [
                'api' => $requestUrl,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        unset($apiParams);

        $decoded = json_decode($resp, true);
        /** @var array<string, mixed> $respObject */
        $respObject = is_array($decoded) ? $decoded : [];

        if (isset($respObject['code']) && $respObject['code'] !== '0') {
            $this->logger->debug('[AliExpress IOP] HTTP_ERROR_', [
                'api' => $requestUrl,
                'code' => $respObject['code'],
                'message' => $respObject['message'] ?? '',
            ]);
        } elseif ($this->logLevel === Constants::$log_level_debug || $this->logLevel === Constants::$log_level_info) {
            $this->logger->debug('[AliExpress IOP] Success', [
                'api' => $requestUrl,
            ]);
        }

        return new IopResponse($respObject, $resp);
    }

    private function msectime(): string
    {
        [$msec, $sec] = explode(' ', microtime());

        return $sec . '000';
    }

    private function endWith(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length == 0) {
            return false;
        }

        return substr($haystack, -$length) === $needle;
    }
}
