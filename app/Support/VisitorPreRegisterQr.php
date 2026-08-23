<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class VisitorPreRegisterQr
{
    public static function preRegisterUrl(): string
    {
        return VisitorPreRegister::preRegisterUrl();
    }

    public static function svg(string $url, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        ));

        return $writer->writeString($url);
    }
}
