<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Struct\Struct;

class PaymentTransactionStruct extends Struct
{
    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var OrderStruct
     */
    protected $order;

    /**
     * @var CalculatedPrice
     */
    protected $amount;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $paymentMethodId;

    public function __construct(
        string $transactionId,
        string $paymentMethodId,
        OrderStruct $order,
        CalculatedPrice $amount,
        string $returnUrl
    ) {
        $this->transactionId = $transactionId;
        $this->order = $order;
        $this->amount = $amount;
        $this->returnUrl = $returnUrl;
        $this->paymentMethodId = $paymentMethodId;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @return OrderStruct
     */
    public function getOrder(): OrderStruct
    {
        return $this->order;
    }

    /**
     * @return CalculatedPrice
     */
    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }
}
