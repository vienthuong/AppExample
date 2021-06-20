<?php declare(strict_types=1);

namespace App\SwagAppsystem\Controller;

use App\Repository\ShopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vin\ShopwareSdk\Data\Webhook\App;
use Vin\ShopwareSdk\Service\WebhookAuthenticator;

class Registration extends AbstractController
{
    /**
     * @Route("/registration", name="register", methods={"GET"})
     */
    public function register(ShopRepository $shopRepository)
    {
        $authenticator = new WebhookAuthenticator();

        $app = new App($_SERVER['APP_NAME'], $_SERVER['APP_SECRET']);

        $response = $authenticator->register($app);

        // shopware.test, id: 123456
        $shopRepository->createShop($response->getShop());

        return new JsonResponse([
            'proof' => $response->getProof(),
            'secret' => $response->getShop()->getShopSecret(),
            'confirmation_url' => $this->generateUrl('confirm', [], UrlGeneratorInterface::ABSOLUTE_URL)
        ]);
    }

    /**
     * @Route("/registration/confirm", name="confirm", methods={"POST"})
     */
    public function confirm(Request $request, ShopRepository $shopRepository): Response
    {
        $requestContent = json_decode($request->getContent(), true);

        $shopSecret = $shopRepository->getSecretByShopId($requestContent['shopId']);

        if (!WebhookAuthenticator::authenticatePostRequest($shopSecret)) {
            return new Response(null, 401);
        }

        $shopRepository->updateAccessKeysForShop($requestContent['shopId'], $requestContent['apiKey'], $requestContent['secretKey']);

        return new Response();
    }
}
