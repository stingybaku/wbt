<?php

namespace App\Filament\Resources\FrontendTextResource\Pages;

use App\Filament\Resources\FrontendTextResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrontendTexts extends ListRecords
{
    protected static string $resource = FrontendTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
