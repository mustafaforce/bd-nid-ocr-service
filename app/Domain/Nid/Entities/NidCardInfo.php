<?php

namespace App\Domain\Nid\Entities;

final readonly class NidCardInfo
{
    public function __construct(
        public ?string $name,
        public ?string $fatherName,
        public ?string $motherName,
        public ?string $address,
        public ?string $nidNumber,
        public ?string $dateOfBirth,
        public ?string $bloodGroup,
        public ?string $issueDate,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'father_name' => $this->fatherName,
            'mother_name' => $this->motherName,
            'address' => $this->address,
            'nid_number' => $this->nidNumber,
            'date_of_birth' => $this->dateOfBirth,
            'blood_group' => $this->bloodGroup,
            'issue_date' => $this->issueDate,
        ];
    }
}
