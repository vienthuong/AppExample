<?php declare(strict_types=1);

namespace App\SwagAppsystem\ArgumentResolver;

use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Vin\ShopwareSdk\Data\Context;
use Vin\ShopwareSdk\Service\WebhookAuthenticator;

class ContextResolver implements ArgumentValueResolverInterface
{
    private ShopRepository $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if ($argument->getType() !== Context::class) {
            return false;
        }

        if ($request->getMethod() === 'POST' && $this->supportsPostRequest($request)) {
            $requestContent = json_decode($request->getContent(), true);
            $shopId = $requestContent['source']['shopId'];

            $shopSecret = $this->shopRepository->getSecretByShopId($shopId);

            return WebhookAuthenticator::authenticatePostRequest($shopSecret);
        } elseif ($request->getMethod() === 'GET' && $this->supportsGetRequest($request)) {
            $shopId = $request->query->get('shop-id');
            $shopSecret = $this->shopRepository->getSecretByShopId($shopId);

            return WebhookAuthenticator::authenticateGetRequest($shopSecret);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        if ($request->getMethod() === 'POST') {
            $requestContent = json_decode($request->getContent(), true);
            $shopId = $requestContent['source']['shopId'];
        } else {
            $shopId = $request->query->get('shop-id');
        }

        yield $this->shopRepository->getShopContext($shopId);
    }

    private function supportsPostRequest(Request $request): bool
    {
        $requestContent = json_decode($request->getContent(), true);

        $hasSource = $requestContent && array_key_exists('source', $requestContent);

        if (!$hasSource) {
            return false;
        }

        $requiredKeys = ['url', 'shopId'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $requestContent['source'])) {
                return false;
            }
        }

        return true;
    }

    private function supportsGetRequest(Request $request): bool
    {
        $query = $request->query->all();

        $requiredKeys = ['shop-url', 'shop-id', 'shopware-shop-signature', 'timestamp'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $query)) {
                return false;
            }
        }

        return true;
    }
}
