<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\StudentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('merchant_id')
                    ->relationship('merchant', 'name')
                    ->required(),
                Select::make('department_id')
                    ->relationship('department', 'name'),
                TextInput::make('student_id_number')
                    ->required(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('middle_name'),
                DatePicker::make('date_of_birth'),
                Select::make('status')
                    ->options(StudentStatus::class)
                    ->default('active')
                    ->required(),
                TextInput::make('parent_name'),
                TextInput::make('parent_phone')
                    ->tel(),
            ]);
    }
}
