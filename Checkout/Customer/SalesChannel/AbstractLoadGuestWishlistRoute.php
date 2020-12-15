<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractLoadGuestWishlistRoute
{
    abstract public function getDecorated(): AbstractLoadWishlistRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): LoadGuestWishlistRouteResponse;
}
