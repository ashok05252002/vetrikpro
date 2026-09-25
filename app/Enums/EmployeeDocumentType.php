<?php

namespace App\Enums;

enum EmployeeDocumentType: string
{
    case IdProof = 'id_proof';
    case AddressProof = 'address_proof';
    case Education = 'education';
    case Experience = 'experience';
    case OfferLetter = 'offer_letter';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IdProof => 'ID proof',
            self::AddressProof => 'Address proof',
            self::Education => 'Education certificate',
            self::Experience => 'Experience letter',
            self::OfferLetter => 'Offer letter',
            self::Contract => 'Contract',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
