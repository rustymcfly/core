<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderDeliveryCollection extends EntityCollection
{
    /**
     * @var OrderDeliveryEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderDeliveryEntity
    {
        return parent::get($id);
    }

    public function current(): OrderDeliveryEntity
    {
        return parent::current();
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getOrderId() === $id;
        });
    }

    public function getShippingAddressIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getShippingOrderAddressId();
        });
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getShippingOrderAddressId() === $id;
        });
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getOrderStateId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
            return $orderDelivery->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (OrderDeliveryEntity $orderDelivery) use ($id) {
            return $orderDelivery->getShippingMethodId() === $id;
        });
    }

    public function getShippingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
                return $orderDelivery->getShippingOrderAddress();
            })
        );
    }

    public function getOrderStates(): OrderStateCollection
    {
        return new OrderStateCollection(
            $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
                return $orderDelivery->getOrderState();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(function (OrderDeliveryEntity $orderDelivery) {
                return $orderDelivery->getShippingMethod();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryEntity::class;
    }
}
