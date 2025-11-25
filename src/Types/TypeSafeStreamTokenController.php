<?php

declare(strict_types=1);

namespace Wioex\SDK\Types;

use PDO;
use PDOException;

/**
 * Type-Safe Stream Token Controller Example
 * Shows how ENUM types prevent runtime errors
 */
class TypeSafeStreamTokenController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate token with type safety - prevents UUID/string confusion
     */
    public function generateToken(string $apiKeyString): array
    {
        try {
            // Type safety: This will throw exception if not valid UUID
            $apiKey = ApiKeyType::fromString($apiKeyString);
            
            // Get API key details with type-safe parameters
            $apiKeyDetails = $this->getApiKeyDetailsTypeSafe($apiKey);
            
            if (!$apiKeyDetails) {
                throw new \InvalidArgumentException('Invalid or inactive API key');
            }

            // Type safety: memberId is guaranteed to be valid UUID
            $memberId = MemberIdType::fromString($apiKeyDetails['member_id']);
            
            // Type safety: TokenType prevents magic strings
            $tokenType = TokenType::fromLiveFlow($apiKeyDetails['live_flow']);
            
            $expiresAt = time() + (30 * 24 * 60 * 60);
            $token = $this->generateJWT($apiKey, $memberId, $tokenType, $expiresAt);
            
            // Type safety: No more int/UUID confusion
            $this->storeTokenTypeSafe($token, $memberId, $expiresAt);

            return [
                'token' => $token,
                'expires_at' => $expiresAt,
                'websocket_url' => $tokenType->getWebSocketUrl(), // Type-safe URL
                'type' => $tokenType->value,
                'api_key_id' => $apiKey->getHash()
            ];

        } catch (\InvalidArgumentException $e) {
            // Type validation errors return 400
            throw new \InvalidArgumentException($e->getMessage());
        } catch (PDOException $e) {
            // Database errors return 503
            throw new \RuntimeException('Database connection failed', 503, $e);
        }
    }

    /**
     * Type-safe API key lookup - no UUID confusion
     */
    private function getApiKeyDetailsTypeSafe(ApiKeyType $apiKey): ?array
    {
        try {
            $sql = 'SELECT ak.id, ak.member_id, ak.live_flow, m.credit
                    FROM "Api_Keys" ak
                    INNER JOIN "Members" m ON ak.member_id = m.member_id
                    WHERE ak.api_key = :api_key
                        AND ak.status = :active_status
                        AND m.credit >= 0
                        AND m.is_active = :member_active
                    LIMIT 1';
            
            $stmt = $this->db->prepare($sql);
            
            // Type safety: FIXED - Use proper boolean values for PostgreSQL
            $stmt->execute([
                'api_key' => $apiKey->toString(),
                'active_status' => true, // FIXED: Use true instead of 1 for PostgreSQL boolean
                'member_active' => true  // Correct boolean value
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (PDOException $e) {
            $connectionStatus = $this->determineConnectionStatus($e);
            if ($connectionStatus->shouldReturn503()) {
                throw new \RuntimeException('Service unavailable', 503, $e);
            }
            throw $e;
        }
    }

    /**
     * Type-safe token storage - no more int/UUID confusion
     */
    private function storeTokenTypeSafe(string $token, MemberIdType $memberId, int $expiresAt): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Type safety: memberId is guaranteed valid UUID
            $deleteSql = 'DELETE FROM stream_tokens WHERE member_id = :member_id';
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute(['member_id' => $memberId->toString()]);
            
            $insertSql = 'INSERT INTO stream_tokens (member_id, token, expires_at, created_at) 
                         VALUES (:member_id, :token, :expires_at, NOW())';
            $insertStmt = $this->db->prepare($insertSql);
            $result = $insertStmt->execute([
                'member_id' => $memberId->toString(), // No type confusion!
                'token' => $token,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt)
            ]);
            
            $this->db->commit();
            return $result;
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Token storage failed', 503, $e);
        }
    }

    private function generateJWT(ApiKeyType $apiKey, MemberIdType $memberId, TokenType $tokenType, int $expiresAt): string
    {
        // Type-safe JWT generation
        $payload = [
            'apiKey' => $apiKey->toString(),
            'memberId' => $memberId->toString(),
            'type' => $tokenType->value,
            'permissions' => ['stream'],
            'iat' => time(),
            'exp' => $expiresAt
        ];
        
        // JWT implementation...
        return 'jwt.token.here';
    }

    private function determineConnectionStatus(PDOException $e): ConnectionStatus
    {
        return match (true) {
            str_contains($e->getMessage(), 'timeout') => ConnectionStatus::TIMEOUT,
            str_contains($e->getMessage(), 'authentication failed') => ConnectionStatus::AUTH_FAILED,
            str_contains($e->getMessage(), 'connection') => ConnectionStatus::DISCONNECTED,
            default => ConnectionStatus::CONNECTED
        };
    }
}

/**
 * Usage Example - Shows benefits of type safety
 */
class ExampleUsage
{
    public function demonstrateTypeSafety(): void
    {
        $controller = new TypeSafeStreamTokenController(new PDO('...'));
        
        try {
            // ✅ Valid UUID - works
            $result = $controller->generateToken('a1b2c3d4-e5f6-7890-abcd-ef1234567890');
            
            // ❌ Invalid UUID - throws exception at compile time with type safety
            // $controller->generateToken('invalid-key'); // Would fail early!
            
        } catch (\InvalidArgumentException $e) {
            // 400 - Bad Request (invalid UUID format)
            http_response_code(400);
        } catch (\RuntimeException $e) {
            // 503 - Service Unavailable (database issues)
            http_response_code($e->getCode());
        }
    }
}