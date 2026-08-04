<?php

namespace App\Helpers;

use Intervention\Image\Facades\Image;

class ImageHelper
{
    /**
     * Convierte una imagen a cuadrada agregando padding blanco.
     * La imagen se centra en un canvas blanco de max(w,h) x max(w,h).
     * Sobreescribe el archivo original.
     */
    public static function makeSquare(string $absolutePath): void
    {
        $img = Image::make($absolutePath);
        $width = $img->width();
        $height = $img->height();

        if ($width === $height) {
            $img->destroy();
            return;
        }

        $size = max($width, $height);
        $square = Image::canvas($size, $size, '#ffffff');
        $square->insert($img, 'center');

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $quality = ($extension === 'png') ? 9 : 85;

        $square->save($absolutePath, $quality);
        $img->destroy();
        $square->destroy();
    }

    /**
     * Genera una miniatura cuadrada de size x size preservando la proporcion.
     *
     * IMPORTANTE: NO se usa resize($size, $size) porque eso ESTIRA la imagen y
     * deforma el contenido (sobre todo el texto de los bosquejos) cuando la fuente
     * no es perfectamente cuadrada. En su lugar se escala manteniendo la proporcion
     * y se centra sobre un lienzo blanco cuadrado (padding), igual que makeSquare().
     */
    public static function makeSquareThumbnail(string $sourcePath, string $thumbPath, int $size = 300, int $quality = 80): void
    {
        $img = Image::make($sourcePath);

        // Escalar para que quepa dentro de size x size SIN deformar (mantiene proporcion).
        $img->resize($size, $size, function ($constraint) {
            $constraint->aspectRatio();
        });

        // Centrar sobre un lienzo blanco cuadrado para que la miniatura siempre sea
        // cuadrada y el contenido nunca se estire (solo se agrega padding si hace falta).
        $canvas = Image::canvas($size, $size, '#ffffff');
        $canvas->insert($img, 'center');

        $canvas->save($thumbPath, $quality);
        $img->destroy();
        $canvas->destroy();
    }
}
