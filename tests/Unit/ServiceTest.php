<?php

namespace Shield\Mailgun\Test\Unit;

use Shield\Mailgun\Mailgun;
use Shield\Testing\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use Illuminate\Support\Carbon;
use Shield\Shield\Contracts\Service;

/**
 * Class ServiceTest
 *
 * @package \Shield\Mailgun\Test\Unit
 */
class ServiceTest extends TestCase
{
    /**
     * @var \Shield\Mailgun\Mailgun
     */
    protected $service;

    protected function setUp()
    {
        parent::setUp();

        $this->service = new Mailgun;
    }

    /** @test */
    public function it_is_a_service()
    {
        Assert::assertInstanceOf(Service::class, new Mailgun);
    }

    /** @test */
    public function it_can_verify_a_valid_request()
    {
        $token = 'raNd0mk3y';
        $this->app['config']['shield.services.mailgun.options.token'] = $token;

        // Build the signature for the request payload
        $timestamp = Carbon::now()->timestamp;
        $signature = $this->buildSignature($timestamp, $token);

        $request = $this->request(json_encode([
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ]));

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $request->headers->add($headers);

        Assert::assertTrue($this->service->verify($request, $this->getConfig()));
    }

    /** @test */
    public function it_will_not_verify_an_old_request()
    {
        $token = 'raNd0mk3y';
        $tolerance = 60;

        $this->app['config']['shield.services.mailgun.options.token'] = $token;
        $this->app['config']['shield.services.mailgun.options.tolerance'] = $tolerance;

        // Build the signature for the request payload
        $timestamp = Carbon::now()->subSeconds($tolerance + 1)->timestamp;
        $signature = $this->buildSignature($timestamp, $token);

        $request = $this->request(json_encode([
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ]));

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $request->headers->add($headers);

        Assert::assertFalse($this->service->verify($request, $this->getConfig()));
    }

    /** @test */
    public function it_will_not_verify_a_bad_request()
    {
        $token = 'good';
        $this->app['config']['shield.services.mailgun.options.token'] = $token;

        // Build the signature for the request payload
        $timestamp = Carbon::now()->timestamp;
        $signature = $this->buildSignature($timestamp, $token);

        $request = $this->request(json_encode([
            'timestamp' => $timestamp,
            'token' => 'bad',
            'signature' => $signature,
        ]));

        $headers = [
            'Content-Type' => 'application/json'
        ];

        $request->headers->add($headers);

        Assert::assertFalse($this->service->verify($request, $this->getConfig()));
    }

    /** @test */
    public function it_requires_a_post_request()
    {
        // Set up valid data
        $token = 'raNd0mk3y';
        $this->app['config']['shield.services.mailgun.options.token'] = $token;

        // Build the signature for the request payload
        $timestamp = Carbon::now()->timestamp;
        $signature = $this->buildSignature($timestamp, $token);

        $requestBody = json_encode([
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ]);

        $examples = ['GET', 'PUT', 'PATCH', 'DELETE', 'POST'];

        foreach ($examples as $example) {

            $request = Request::create('http://example.com', $example, [], [], [], [], $requestBody);
            $request->headers->add([
                'Content-Type' => 'application/json'
            ]);

            $assertion = $example === 'POST' ? 'assertTrue' : 'assertFalse';

            Assert::$assertion(
                $this->service->verify($request, $this->getConfig()),
                "Expected $example to $assertion, but it did not."
            );
        }
    }

    /** @test */
    public function it_has_correct_headers_required()
    {
        Assert::assertArraySubset([], $this->service->headers());
    }

    /**
     * Get a configuration value for mailgun
     * @param $value An optional value
     * @param $default When a value is requested, a default value
     * 
     * @return Collection|string
     */
    protected function getConfig($value = null, $default = null)
    {
        $config = collect($this->app['config']['shield.services.mailgun.options']);

        if ($value === null) {
            return $config;
        }

        return $config->get($value, $default);
    }

    protected function buildSignature($timestamp, $token)
    {
        return hash_hmac(
            'sha256',
            sprintf('%s%s', $timestamp, $token),
            $this->getConfig('token')
        );
    }
}
