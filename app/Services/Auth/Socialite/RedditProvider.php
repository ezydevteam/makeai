<?php

namespace App\Services\Auth\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class RedditProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['identity'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://www.reddit.com/api/v1/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://www.reddit.com/api/v1/access_token';
    }

    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                'User-Agent' => $this->userAgent(),
            ],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://oauth.reddit.com/api/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'User-Agent' => $this->userAgent(),
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => $user['name'] ?? null,
            'name' => $user['subreddit']['display_name'] ?? $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['icon_img'] ?? null,
        ]);
    }

    private function userAgent(): string
    {
        return str_replace(' ', '-', settings('app_name', translate('Application'))).' '.translate('Social Login');
    }
}
