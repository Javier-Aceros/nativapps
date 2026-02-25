<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Infrastructure\AI\AiProviderFactory;
use App\Infrastructure\Channels\EmailChannelAdapter;
use App\Infrastructure\Channels\SlackChannelAdapter;
use App\Infrastructure\Channels\SmsChannelAdapter;
use App\Infrastructure\Resolvers\ChannelAdapterResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Channel adapters are singletons so their config is read once.
     * To add a new channel: create a NotificationProvider class and
     * add it to the $adapters array below — nothing else changes.
     *
     * To swap the AI provider: set AI_PROVIDER in .env (openai|gemini|anthropic).
     */
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, fn () => AiProviderFactory::make(
            (string) config('services.ai.provider', 'gemini'),
        ));

        $this->app->singleton(SlackChannelAdapter::class, fn () => new SlackChannelAdapter(
            webhookUrl: (string) config('services.slack.webhook_url'),
        ));

        $this->app->singleton(SmsChannelAdapter::class, fn () => new SmsChannelAdapter(
            destination: (string) config('services.sms.destination'),
        ));

        $this->app->singleton(ChannelAdapterResolver::class, function ($app) {
            return new ChannelAdapterResolver([
                $app->make(EmailChannelAdapter::class),
                $app->make(SlackChannelAdapter::class),
                $app->make(SmsChannelAdapter::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Listener registration is handled by Laravel 12's automatic event discovery
        // (app/Listeners/DispatchChannelsListener → handle(MessageProcessed $event)).
        // No manual Event::listen() needed; adding one would register it twice.
    }
}
