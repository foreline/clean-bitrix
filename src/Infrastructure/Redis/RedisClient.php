<?php
declare(strict_types=1);

namespace Infrastructure\Redis;

use Exception;
use Predis\Client as PredisClient;
use Predis\Response\Status;

/**
 * Predis Client wrapper
 */
class RedisClient
{
    private ?PredisClient $client = null;
    
    /**
     *
     */
    public function __construct(?PredisClient $client = null)
    {
        if ( empty($_ENV['REDIS_HOST']) ) {
            return;
        }
        
        $this->client = $client ?? new PredisClient([
            'scheme'   => $_ENV['REDIS_SCHEME'] ?: 'tcp',
            'host'     => $_ENV['REDIS_HOST'] ?: '127.0.0.1',
            'port'     => $_ENV['REDIS_PORT'] ?: 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?: '',
            'database' => 0,
        ]);
    }
    
    /**
     * @param string $channel
     * @param string $message
     * @return ?int
     */
    public function publish(string $channel, string $message): ?int
    {
        return $this->client?->publish($channel, $message);
    }
    
    /**
     * @param string $message
     * @return int|null
     * @throws Exception
     */
    public function publishMessage(string $message): ?int
    {
        $channel = bitrix_sessid_val();
        if ( empty($channel) ) {
            return null;
        }
        return $this->publish($channel, $message);
    }
    
    /**
     *
     * @param string $key
     * @param int $ttl
     * @param string $value
     * @return ?Status
     */
    public function setex(string $key, string $value, int $ttl = 3600): ?Status
    {
        return $this->client?->setex($key, $ttl, $value);
    }
    
    /**
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string
    {
        return $this->client?->get($key);
    }
    
    /**
     * @param int $userId
     * @param string $message
     * @return ?int[]
     */
    public function publishForUser(int $userId, string $message): ?array
    {
        // Получить все сессии пользователя
        // @fixme @todo реализовать получение сессий пользователя из Redis
    }
    
}