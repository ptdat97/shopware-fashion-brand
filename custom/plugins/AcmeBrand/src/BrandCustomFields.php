<?php declare(strict_types=1);

namespace Acme\Brand;

/**
 * Tên custom field của brand trên `product_manufacturer` (dịch được, hiện sẵn trong
 * thẻ custom field của trang nhà sản xuất ở Administration).
 */
final class BrandCustomFields
{
    public const SET_NAME = 'acme_brand_seo';

    public const META_TITLE = 'acme_brand_meta_title';

    public const META_DESCRIPTION = 'acme_brand_meta_description';

    public const KEYWORDS = 'acme_brand_keywords';
}
