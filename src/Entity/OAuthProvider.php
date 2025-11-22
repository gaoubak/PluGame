<?php

namespace App\Entity;

use App\Entity\Traits\Timestamps;
use App\Entity\Traits\UuidId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'oauth_provider')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['provider', 'provider_user_id'], name: 'idx_provider_user')]
class OAuthProvider
{
    use UuidId;
    use Timestamps;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'oauthProviders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * OAuth provider name (google, apple)
     */
    #[ORM\Column(type: 'string', length: 50)]
    private string $provider;

    /**
     * User ID from the OAuth provider
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $providerUserId;

    /**
     * Email from OAuth provider
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerEmail = null;

    /**
     * Full name from OAuth provider
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerName = null;

    /**
     * Profile photo URL from OAuth provider
     */
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $providerPhotoUrl = null;

    /**
     * Raw OAuth data (JSON)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $providerData = null;

    /**
     * OAuth access token (encrypted in production)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accessToken = null;

    /**
     * OAuth refresh token (encrypted in production)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refreshToken = null;

    /**
     * Token expiration timestamp
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    public function __construct(User $user, string $provider, string $providerUserId)
    {
        $this->user = $user;
        $this->provider = $provider;
        $this->providerUserId = $providerUserId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): self
    {
        $this->providerUserId = $providerUserId;
        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(?string $providerEmail): self
    {
        $this->providerEmail = $providerEmail;
        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(?string $providerName): self
    {
        $this->providerName = $providerName;
        return $this;
    }

    public function getProviderPhotoUrl(): ?string
    {
        return $this->providerPhotoUrl;
    }

    public function setProviderPhotoUrl(?string $providerPhotoUrl): self
    {
        $this->providerPhotoUrl = $providerPhotoUrl;
        return $this;
    }

    public function getProviderData(): ?array
    {
        return $this->providerData;
    }

    public function setProviderData(?array $providerData): self
    {
        $this->providerData = $providerData;
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): self
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    /**
     * Check if the access token is still valid
     */
    public function isTokenValid(): bool
    {
        if (!$this->accessToken || !$this->tokenExpiresAt) {
            return false;
        }

        return $this->tokenExpiresAt > new \DateTimeImmutable();
    }
}
