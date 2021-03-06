<?php

namespace blink\restapi;

use blink\core\BaseObject;
use blink\core\MiddlewareContract;
use blink\core\HttpException;
use blink\http\Response;
use blink\support\Json;
use rethink\typedphp\TypeParser;
use rethink\typedphp\TypeValidator;

/**
 * Class ResponseValidator
 *
 * @package blink\restapi
 */
class ResponseValidator extends BaseObject implements MiddlewareContract {

    public $responses = [];

    /**
     * @param Response $response
     * @throws HttpException
     */
    public function handle($response)
    {
        $responses = $this->responses;
        $code = $response->getStatusCode();

        if (! array_key_exists($code, $responses)) {
            throw new HttpException(500, "The response schema of status code: $code is not defined");
        }

        if ($responses[$code] === null) {
            if ($response->data !== null) {
                throw new HttpException(500, "The response of status code: $code is incorrect, no response body required");
            }
            return;
        }

        $data = $response->data;
        if ($data === null) {
            $data = Json::decode((string)$response->getBody());
        } else {
            $data = Json::decode(Json::encode($data));
        }

        $definition = app()->restapi->makeTypeParser(TypeParser::MODE_JSON_SCHEMA)->parse($responses[$code]);

        $validator = new TypeValidator();
        if (!$validator->validate($data, $definition)) {
            throw new HttpException(500, sprintf(
                "Response schema validation failed. error: %s, response data: %s",
                Json::encode($validator->getErrors()),
                Json::encode($data)
            ));
        }
    }
}
