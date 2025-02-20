<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Behat\Context\Api\Admin;

use ApiPlatform\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Sylius\Behat\Client\ApiClientInterface;
use Sylius\Behat\Client\ResponseCheckerInterface;
use Sylius\Behat\Context\Api\Resources;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

final class ManagingProductVariantsContext implements Context
{
    private const FIRST_COLLECTION_ITEM = 0;

    public function __construct(
        private ApiClientInterface $client,
        private ResponseCheckerInterface $responseChecker,
        private IriConverterInterface $iriConverter,
    ) {
    }

    /**
     * @When /^I want to create a new variant of (this product)$/
     */
    public function iWantToCreateANewProductVariant(ProductInterface $product): void
    {
        $this->client->buildCreateRequest(Resources::PRODUCT_VARIANTS);
        $this->client->addRequestData('product', $this->iriConverter->getIriFromResource($product));
    }

    /**
     * @When I specify its code as :code
     */
    public function iSpecifyItsCodeAs(string $code): void
    {
        $this->client->addRequestData('code', $code);
    }

    /**
     * @When I name it :name in :localeCode
     */
    public function iNameItIn(string $name, string $localeCode): void
    {
        $this->client->addRequestData('translations', [
            $localeCode => [
                'locale' => $localeCode,
                'name' => $name,
            ],
        ]);
    }

    /**
     * @When /^I set its price to ("[^"]+") for ("[^"]+" channel)$/
     */
    public function iSetItsPriceToForChannel(int $price, ChannelInterface $channel): void
    {
        $this->client->addRequestData('channelPricings', [
            $channel->getCode() => [
                'price' => $price,
                'channelCode' => $channel->getCode(),
            ],
        ]);
    }

    /**
     * @When /^I set its original price to ("[^"]+") for ("[^"]+" channel)$/
     */
    public function iSetItsOriginalPriceToForChannel(int $originalPrice, ChannelInterface $channel): void
    {
        $this->client->addRequestData('channelPricings', [
            $channel->getCode() => [
                'originalPrice' => $originalPrice,
                'channelCode' => $channel->getCode(),
            ],
        ]);
    }

    /**
     * @When /^I set its minimum price to ("[^"]+") for ("[^"]+" channel)$/
     */
    public function iSetItsMinimumPriceToForChannel(int $minimumPrice, ChannelInterface $channel): void
    {
        $content = $this->client->getContent();
        $content['channelPricings'][$channel->getCode()]['minimumPrice'] = $minimumPrice;

        $this->client->updateRequestData($content);
    }

    /**
     * @When I add it
     */
    public function iAddIt(): void
    {
        $this->client->create();
    }

    /**
     * @When /^I change the price of the ("[^"]+" product variant) to ("[^"]+") in ("[^"]+" channel)$/
     */
    public function iChangeThePriceOfTheProductVariantInChannel(
        ProductVariantInterface $variant,
        int $price,
        ChannelInterface $channel,
    ): void {
        $this->updateChannelPricingField($variant, $channel, $price, 'price');
    }

    /**
     * @When /^I change the original price of the ("[^"]+" product variant) to ("[^"]+") in ("[^"]+" channel)$/
     */
    public function iChangeTheOriginalPriceOfTheProductVariantInChannel(
        ProductVariantInterface $variant,
        int $originalPrice,
        ChannelInterface $channel,
    ): void {
        $this->updateChannelPricingField($variant, $channel, $originalPrice, 'originalPrice');
    }

    /**
     * @When /^I remove the original price of the ("[^"]+" product variant) in ("[^"]+" channel)$/
     */
    public function iRemoveTheOriginalPriceOfTheProductVariantInChannel(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
    ): void {
        $this->updateChannelPricingField($variant, $channel, null, 'originalPrice');
    }

    /**
     * @When /^I create a new "([^"]+)" variant priced at ("[^"]+") for ("[^"]+" product) in the ("[^"]+" channel)$/
     */
    public function iCreateANewVariantPricedAtForProductInTheChannel(
        string $name,
        int $price,
        ProductInterface $product,
        ChannelInterface $channel,
    ): void {
        $this->createNewVariantWithPrice($name, $price, $product, $channel);
    }

    /**
     * @When I want to modify the :variant product variant
     */
    public function iWantToModifyProductVariant(ProductVariantInterface $variant): void
    {
        $this->client->buildUpdateRequest(Resources::PRODUCT_VARIANTS, $variant->getCode());
    }

    /**
     * @When /^I change its price to ("[^"]+") for ("[^"]+" channel)$/
     */
    public function iChangeItsPriceToForChannel(int $originalPrice, ChannelInterface $channel): void
    {
        $this->client->addRequestData('channelPricings', [
            $channel->getCode() => [
                'price' => $originalPrice,
                'channelCode' => $channel->getCode(),
            ],
        ]);
    }

    /**
     * @Then I should be notified that it has been successfully created
     */
    public function iShouldBeNotifiedThatItHasBeenSuccessfullyCreated(): void
    {
        Assert::true(
            $this->responseChecker->isCreationSuccessful($this->client->getLastResponse()),
            sprintf(
                'Product Variant could not be created: %s',
                $this->responseChecker->getError($this->client->getLastResponse()),
            ),
        );
    }

    /**
     * @Then the :productVariantCode variant of the :product product should appear in the store
     */
    public function theProductVariantShouldAppearInTheShop(string $productVariantCode, ProductInterface $product): void
    {
        $response = $this->client->index(Resources::PRODUCT_VARIANTS);

        Assert::true($this->responseChecker->hasItemWithValue($response, 'code', $productVariantCode));
    }

    /**
     * @Then /^the (?:variant with code "[^"]+") should be named "([^"]+)" in ("([^"]+)" locale)$/
     */
    public function theVariantWithCodeShouldBeNamedIn(string $name, string $localeCode): void
    {
        $response = $this->responseChecker->getCollection($this->client->index(Resources::PRODUCT_VARIANTS));

        $expectedTranslation = [
            'locale' => $localeCode,
            'name' => $name,
        ];

        $translationInLocale = $response[self::FIRST_COLLECTION_ITEM]['translations'][$localeCode];

        Assert::allInArray(
            $expectedTranslation,
            $translationInLocale,
            sprintf('Expected translation %s, got %s', $expectedTranslation['name'], $translationInLocale['name']),
        );
    }

    /**
     * @Then /^the (variant with code "[^"]+") should be priced at ("[^"]+") for (channel "([^"]+)")$/
     */
    public function theVariantWithCodeShouldBePricedAtForChannel(ProductVariantInterface $productVariant, int $price, ChannelInterface $channel): void
    {
        $response = $this->responseChecker->getCollection($this->client->index(Resources::PRODUCT_VARIANTS));

        Assert::same($response[self::FIRST_COLLECTION_ITEM]['channelPricings'][$channel->getCode()]['price'], $price);
    }

    /**
     * @Then /^the (variant with code "[^"]+") should have minimum price ("[^"]+") for (channel "([^"]+)")$/
     */
    public function theVariantWithCodeShouldHaveMinimumPriceForChannel(ProductVariantInterface $productVariant, int $minimumPrice, ChannelInterface $channel): void
    {
        $response = $this->responseChecker->getCollection($this->client->index(Resources::PRODUCT_VARIANTS));

        Assert::same($response[self::FIRST_COLLECTION_ITEM]['channelPricings'][$channel->getCode()]['minimumPrice'], $minimumPrice);
    }

    private function updateChannelPricingField(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        ?int $price,
        string $field,
    ): void {
        $this->client->buildUpdateRequest(Resources::PRODUCT_VARIANTS, $variant->getCode());

        $content = $this->client->getContent();
        $content['channelPricings'][$channel->getCode()][$field] = $price;
        $this->client->updateRequestData($content);

        $this->client->update();
    }

    private function createNewVariantWithPrice(
        string $name,
        int $price,
        ProductInterface $product,
        ChannelInterface $channel,
    ): void {
        $this->client->buildCreateRequest(Resources::PRODUCT_VARIANTS);
        $this->client->addRequestData('product', $this->iriConverter->getIriFromResource($product));
        $this->client->addRequestData('code', StringInflector::nameToCode($name));

        $this->client->addRequestData('channelPricings', [
            $channel->getCode() => [
                'price' => $price,
                'channelCode' => $channel->getCode(),
            ],
        ]);

        $this->client->create();
    }
}
