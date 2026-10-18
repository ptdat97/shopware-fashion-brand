<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BrandEntity extends Entity
{
    use EntityIdTrait;

    protected string $productManufacturerId;

    protected string $productManufacturerVersionId;

    protected ?string $code = null;

    protected bool $active = false;

    protected bool $featured = false;

    protected int $position = 0;

    protected ?string $bannerMediaId = null;

    protected ?string $cmsPageId = null;

    protected ?string $cmsPageVersionId = null;

    protected ?ProductManufacturerEntity $productManufacturer = null;

    protected ?MediaEntity $bannerMedia = null;

    protected ?CmsPageEntity $cmsPage = null;


    public function getProductManufacturerId(): string
    {
        return $this->productManufacturerId;
    }

    public function setProductManufacturerId(string $productManufacturerId): void
    {
        $this->productManufacturerId = $productManufacturerId;
    }

    public function getProductManufacturerVersionId(): string
    {
        return $this->productManufacturerVersionId;
    }

    public function setProductManufacturerVersionId(string $productManufacturerVersionId): void
    {
        $this->productManufacturerVersionId = $productManufacturerVersionId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): void
    {
        $this->featured = $featured;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getBannerMediaId(): ?string
    {
        return $this->bannerMediaId;
    }

    public function setBannerMediaId(?string $bannerMediaId): void
    {
        $this->bannerMediaId = $bannerMediaId;
    }

    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(?string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getCmsPageVersionId(): ?string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(?string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
    }

    public function getProductManufacturer(): ?ProductManufacturerEntity
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(?ProductManufacturerEntity $productManufacturer): void
    {
        $this->productManufacturer = $productManufacturer;
    }

    public function getBannerMedia(): ?MediaEntity
    {
        return $this->bannerMedia;
    }

    public function setBannerMedia(?MediaEntity $bannerMedia): void
    {
        $this->bannerMedia = $bannerMedia;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(?CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }
}
