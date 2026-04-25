<?php

namespace App\Application\Nid\DTOs;

use App\Domain\Nid\Entities\NidCardInfo;

final readonly class ExtractNidDataResult
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public NidCardInfo $cardInfo,
        public string $rawFrontText,
        public string $rawBackText,
        public array $warnings,
    ) {
    }

    public function toArray(): array
    {
        return [
            'data' => [
                'name' => $this->cardInfo->name,
                'father_name' => $this->cardInfo->fatherName,
                'mother_name' => $this->cardInfo->motherName,
                'address' => $this->cardInfo->address,
                'nid_number' => $this->cardInfo->nidNumber,
                'date_of_birth' => $this->cardInfo->dateOfBirth,
                'blood_group' => $this->cardInfo->bloodGroup,
                'issue_date' => $this->cardInfo->issueDate,
            ],
            'raw_text' => [
                'front' => $this->rawFrontText,
                'back' => $this->rawBackText,
            ],
            'warnings' => $this->warnings,
        ];
    }
}
