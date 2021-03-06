<?php

namespace Ajuchacko\Payu;

use Ajuchacko\Payu\Checksum;
use Ajuchacko\Payu\Concerns\HasConfig;
use Ajuchacko\Payu\Concerns\HasParams;
use Ajuchacko\Payu\Enums\PaymentStatusType;
use Ajuchacko\Payu\Exceptions\InvalidChecksumException;
use Ajuchacko\Payu\Exceptions\PaymentFailedException;
use Ajuchacko\Payu\HttpResponse;
use Exception;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PayuGateway
{
    use HasConfig, HasParams;

	const TEST_URL = 'https://sandboxsecure.payu.in/_payment';

    const PRODUCTION_URL = 'https://secure.payu.in/_payment';

    /**
     * Payment gateway provided id.
     *
     * @var string
     */
    private $merchant_id;

    /**
     * Payment gateway provided secret key.
     *
     * @var string
     */
    private $secret_key;

    /**
     * Denotes is in test mode.
     *
     * @var bool
     */
    private $test_mode;

    /**
     * Generated checksum instance
     *
     * @var Ajuchacko\Payu\Checksum
     */
    private $checksum;

    /**
     * Params provided
     *
     * @var array
     */
    private $params;

    /**
     * Create an instance of PayuGateway
     * 
     * @param  array $options
     * @return void
     */
    public function __construct(array $options)
    {
        $resolver = (new OptionsResolver())
            ->setRequired(['merchant_id', 'merchant_key', 'secret_key', 'test_mode'])
            ->setAllowedTypes('merchant_id', 'string')
            ->setAllowedTypes('merchant_key', 'string')
            ->setAllowedTypes('secret_key', 'string')
            ->setAllowedTypes('test_mode', 'bool');

        $options = $resolver->resolve($options);

        $this->merchant_id = $options['merchant_id'];
        $this->merchant_key = $options['merchant_key'];
        $this->secret_key = $options['secret_key'];
        $this->test_mode = $options['test_mode'];
    }

    /**
     * Get payment url.
     *
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->test_mode ? self::TEST_URL : self::PRODUCTION_URL;
    }

    /**
     * Creates a checksum
     * 
     * @param  array $params
     * @return string
     */
    public function newChecksum(array $params): string
    {
        $this->setParams($params);

        $all_params = array_merge($this->getParams(), [
            'merchant_key' => $this->getMerchantKey(),
            'secret_key'  => $this->getSecretKey(),
            'test_mode'   => $this->getTestMode(),
        ]);

        $this->checksum = Checksum::create($all_params);

        return $this->checksum->getHash();
    }
    
    /**
     * Creates a Http Response to submit using the params
     * 
     * @param  array $params
     * @return Symfony\Component\HttpFoundation\Response;
     */
    public function pay(array $params)
    {
        $this->setParams($params);

        $params = array_merge($params, ['hash' => $this->newChecksum($this->getParams()), 'key' => $this->getMerchantKey()]);

        return HttpResponse::make($params, $this->getPaymentUrl());
    }
    
    /**
     * Creates the payment response from the provided response array 
     * 
     * @param  array $response
     * @return Ajuchacko\Payu\PaymentResponse
     * 
     * @throws Ajuchacko\Payu\Exceptions\InvalidChecksumException
     */
    public function getPaymentResponse(array $response)
    {
        if (! Checksum::valid($response, $this)) {
            throw new InvalidChecksumException;
        }

        return PaymentResponse::make($response);
    }
    
    /**
     * Checks payment has success status
     * 
     * @param  array $response
     * @return Ajuchacko\Payu\PaymentResponse
     * 
     * @throws Ajuchacko\Payu\Exceptions\PaymentFailedException
     */
    public function paymentSuccess(array $response)
    {
        $response = $this->getPaymentResponse($response);
        if ($response->getStatus() === PaymentStatusType::STATUS_COMPLETED) {
            return $response;
        }
        throw new PaymentFailedException;
    }
    
    /**
     * Converts instance to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'merchant_id' => $this->getMerchantId(),
            'merchant_key'=> $this->getMerchantKey(),
            'txnid'       => $this->txnid,
            'amount'      => $this->amount,
            'productinfo' => $this->productinfo,
            'firstname'   => $this->firstname,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'surl'        => $this->surl,
            'furl'        => $this->furl,
            'hash'        => $this->checksum->getHash(),
            'sandbox'     => $this->getTestMode(),
        ];
    }
}
