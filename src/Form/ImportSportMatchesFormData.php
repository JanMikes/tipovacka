<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportSportMatchesFormData
{
    #[Assert\NotNull(message: 'Vyberte prosím soubor k nahrání.')]
    #[Assert\File(
        maxSize: '2M',
        maxSizeMessage: 'Soubor je příliš velký (maximálně 2 MB).',
        mimeTypes: [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/octet-stream',
        ],
        mimeTypesMessage: 'Nahrajte soubor ve formátu XLSX nebo CSV.',
    )]
    public ?UploadedFile $file = null;
}
