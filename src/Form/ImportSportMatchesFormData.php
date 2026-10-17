<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportSportMatchesFormData
{
    #[Assert\NotNull(message: 'Vyberte prosím soubor XLSX nebo CSV se zápasy (vzor si stáhněte jako šablonu výše).')]
    #[Assert\File(
        maxSize: '2M',
        maxSizeMessage: 'Soubor je příliš velký — maximum jsou 2 MB. Rozdělte zápasy do více souborů.',
        mimeTypes: [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/octet-stream',
        ],
        mimeTypesMessage: 'Tento typ souboru neumíme načíst. Nahrajte prosím XLSX nebo CSV (v Excelu Soubor → Uložit jako).',
    )]
    public ?UploadedFile $file = null;
}
