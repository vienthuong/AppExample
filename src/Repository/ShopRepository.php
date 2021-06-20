<?php declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Vin\ShopwareSdk\Client\AdminAuthenticator;
use Vin\ShopwareSdk\Client\GrantType\ClientCredentialsGrantType;
use Vin\ShopwareSdk\Data\Context;
use Vin\ShopwareSdk\Data\Webhook\Shop;

class ShopRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function updateAccessKeysForShop(string $shopId, string $apiKey, string $secretKey): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->update('shop')
            ->set('api_key', ':api_key')
            ->set('secret_key', ':secret_key')
            ->where('shop_id = :shop_id')
            ->setParameter('api_key', $apiKey)
            ->setParameter('secret_key', $secretKey)
            ->setParameter('shop_id', $shopId);
        $queryBuilder->execute();
    }

    public function createShop(Shop $shop): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->insert('shop')
            ->setValue('shop_id', ':shop_id')
            ->setValue('shop_url', ':shop_url')
            ->setValue('shop_secret', ':shop_secret')
            ->setParameter('shop_id', $shop->getShopId())
            ->setParameter('shop_url', $shop->getShopUrl())
            ->setParameter('shop_secret', $shop->getShopSecret());
        $queryBuilder->execute();
    }

    public function removeShop(string $shopId): void
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->delete('shop')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shopId);
        $queryBuilder->execute();
    }

    public function getSecretByShopId(string $shopId): string
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('shop_secret')
            ->from('shop')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shopId);
        $query = $queryBuilder->execute();

        $data = $query->fetch();

        return $data['shop_secret'];
    }

    public function getShopContext(string $shopId): Context
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->select('shop_url', 'api_key', 'secret_key')
            ->from('shop')
            ->where('shop_id = :shop_id')
            ->setParameter('shop_id', $shopId);
        $query = $queryBuilder->execute();

        $data = $query->fetchAssociative();

        $authenticator = new AdminAuthenticator(new ClientCredentialsGrantType($data['api_key'], $data['secret_key']), $data['shop_url']);

        $token = $authenticator->fetchAccessToken();

        return new Context($data['shop_url'], $token);
    }
}
