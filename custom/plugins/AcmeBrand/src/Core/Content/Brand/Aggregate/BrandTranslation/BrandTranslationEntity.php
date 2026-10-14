<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\Aggregate\BrandTranslation;

use Acme\Brand\Core\Content\Brand\BrandEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class BrandTranslationEntity extends TranslationEntity
{
    protected string $acmeBrandId;

    protected ?BrandEntity $acmeBrand = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    public function getAcmeBrandId(): string
    {
        return $this->acmeBrandId;
    }

    public function setAcmeBrandId(string $acmeBrandId): void
    {
        $this->acmeBrandId = $acmeBrandId;
    }

    public function getAcmeBrand(): ?BrandEntity
    {
        return $this->acmeBrand;
    }

    public function setAcmeBrand(?BrandEntity $acmeBrand): void
    {
        $this->acmeBrand = $acmeBrand;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }
}
