<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;

class DeliveryBuilder
{
    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    public function __construct(QuantityPriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    public function build(DeliveryCollection $deliveries, LineItemCollection $items, CheckoutContext $context): DeliveryCollection
    {
        /** @var LineItem $item */
        foreach ($items as $item) {
            if (!$item->getDeliveryInformation()) {
                continue;
            }

            if ($deliveries->contains($item)) {
                continue;
            }

            $quantity = $item->getQuantity();

            $position = new DeliveryPosition(
                $item->getKey(),
                clone $item,
                $quantity,
                $item->getPrice(),
                $item->getDeliveryInformation()->getInStockDeliveryDate()
            );

            //completely in stock?
            if ($item->getDeliveryInformation()->getStock() >= $quantity) {
                $this->addGoodsToDelivery(
                    $deliveries,
                    $position,
                    $context->getShippingLocation(),
                    $context->getShippingMethod()
                );
                continue;
            }

            //completely out of stock? add full quantity to a delivery with same of out stock delivery date
            if ($item->getDeliveryInformation()->getStock() <= 0) {
                $position = new DeliveryPosition(
                    $item->getKey(),
                    clone $item,
                    $quantity,
                    $item->getPrice(),
                    $item->getDeliveryInformation()->getOutOfStockDeliveryDate()
                );

                $this->addGoodsToDelivery(
                    $deliveries,
                    $position,
                    $context->getShippingLocation(),
                    $context->getShippingMethod()
                );
                continue;
            }

            $outOfStock = (int) abs($item->getDeliveryInformation()->getStock() - $quantity);

            $position = $this->recalculatePosition(
                $item,
                $item->getDeliveryInformation()->getStock(),
                $item->getDeliveryInformation()->getInStockDeliveryDate(),
                $context
            );

            $this->addGoodsToDelivery(
                $deliveries,
                $position,
                $context->getShippingLocation(),
                $context->getShippingMethod()
            );

            $position = $this->recalculatePosition(
                $item,
                $outOfStock,
                $item->getDeliveryInformation()->getOutOfStockDeliveryDate(),
                $context
            );

            $this->addGoodsToDelivery(
                $deliveries,
                $position,
                $context->getShippingLocation(),
                $context->getShippingMethod()
            );
        }

        return $deliveries;
    }

    private function recalculatePosition(
        LineItem $item,
        int $quantity,
        DeliveryDate $deliveryDate,
        CheckoutContext $context
    ): DeliveryPosition {
        $definition = new QuantityPriceDefinition(
            $item->getPrice()->getUnitPrice(),
            $item->getPrice()->getTaxRules(),
            $quantity,
            true
        );

        $price = $this->priceCalculator->calculate($definition, $context);

        return new DeliveryPosition(
            $item->getKey(),
            clone $item,
            $quantity,
            $price,
            $deliveryDate
        );
    }

    private function addGoodsToDelivery(
        DeliveryCollection $deliveries,
        DeliveryPosition $position,
        ShippingLocation $location,
        ShippingMethodStruct $shippingMethod
    ): void {
        $delivery = $deliveries->getDelivery(
            $position->getDeliveryDate(),
            $location
        );

        if ($delivery) {
            $delivery->getPositions()->add($position);

            return;
        }

        $delivery = new Delivery(
            new DeliveryPositionCollection([$position]),
            $position->getDeliveryDate(),
            $shippingMethod,
            $location,
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $deliveries->add($delivery);
    }
}
