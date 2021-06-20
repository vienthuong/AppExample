<?php declare(strict_types=1);

namespace App\SwagAppsystem\ArgumentResolver;

use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Vin\ShopwareSdk\Data\Webhook\AppAction\AppAction;
use Vin\ShopwareSdk\Service\WebhookAuthenticator;

class AppActionResolver implements ArgumentValueResolverInterface
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if ($argument->getType() !== AppAction::class) {
            return false;
        }

        if ($request->getMethod() !== 'POST') {
            return false;
        }

        $requestContent = json_decode($request->getContent(), true);

        if (!$requestContent) {
            return false;
        }

        $hasSource = array_key_exists('source', $requestContent);
        $hasData = array_key_exists('data', $requestContent);
        $hasSourceAndData = $hasSource && $hasData;

        if (!$hasSourceAndData) {
            return false;
        }

        $requiredKeys = ['url', 'appVersion', 'shopId'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $requestContent['source'])) {
                return false;
            }
        }

        $shopSecret = $this->shopRepository->getSecretByShopId($requestContent['source']['shopId']);

        return WebhookAuthenticator::authenticatePostRequest($shopSecret);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $requestContent = json_decode($request->getContent(), true);

        yield AppAction::createFromPayload($requestContent, $request->headers->all());
    }
}
