<?php

namespace App\Support;

/**
 * The crypto networks users may be paid on. Stablecoins only: they are USD-pegged, so a USD payout amount IS the
 * coin amount and no price conversion (or rate risk) sits between the user's balance and the wallet.
 *
 * `currency` is the NOWPayments ticker. Anything wrong here fails closed: the address is validated against
 * NOWPayments' own POST /v1/payout/validate-address before it is ever saved, so a bad ticker is rejected, never paid.
 */
final class CryptoPayoutNetworks
{
    private const EVM = '/^0x[a-fA-F0-9]{40}$/';

    /** @var array<string, array{label: string, coin: string, chain: string, currency: string, pattern: string, memo: bool, example: string}> */
    private const NETWORKS = [
        'usdttrc20' => ['label' => 'USDT on Tron (TRC20)', 'coin' => 'USDT', 'chain' => 'Tron', 'currency' => 'usdttrc20', 'pattern' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/', 'memo' => false, 'example' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS'],
        'usdterc20' => ['label' => 'USDT on Ethereum (ERC20)', 'coin' => 'USDT', 'chain' => 'Ethereum', 'currency' => 'usdterc20', 'pattern' => self::EVM, 'memo' => false, 'example' => '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'],
        'usdtbsc' => ['label' => 'USDT on BNB Smart Chain (BEP20)', 'coin' => 'USDT', 'chain' => 'BNB Smart Chain', 'currency' => 'usdtbsc', 'pattern' => self::EVM, 'memo' => false, 'example' => '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'],
        'usdcerc20' => ['label' => 'USDC on Ethereum (ERC20)', 'coin' => 'USDC', 'chain' => 'Ethereum', 'currency' => 'usdcerc20', 'pattern' => self::EVM, 'memo' => false, 'example' => '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'],
        'usdtsol' => ['label' => 'USDT on Solana', 'coin' => 'USDT', 'chain' => 'Solana', 'currency' => 'usdtsol', 'pattern' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', 'memo' => false, 'example' => 'DYw8jCTfwHNRJhhmFcbXvVDTqWMEVFBX6ZKUmG5CNSKK'],
    ];

    /** @return array<string, array{label: string, coin: string, chain: string, currency: string, pattern: string, memo: bool, example: string}> */
    public static function all(): array
    {
        return self::NETWORKS;
    }

    /** @return array{label: string, coin: string, chain: string, currency: string, pattern: string, memo: bool, example: string}|null */
    public static function get(string $key): ?array
    {
        return self::NETWORKS[strtolower($key)] ?? null;
    }

    /** Does the address have the right SHAPE for the network? (The provider's own check follows.) */
    public static function looksValid(string $key, string $address): bool
    {
        $n = self::get($key);

        return $n !== null && preg_match($n['pattern'], $address) === 1;
    }
}
