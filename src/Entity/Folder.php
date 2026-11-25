<?php

namespace App\Entity;

use App\Repository\FolderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FolderRepository::class)]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, nullable: false)]
    #[Assert\NotBlank(message: 'Donnez un nom à ce dossier.', normalizer: 'trim')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getPath(): string
    {
        $names = [];

        for ($folder = $this; null !== $folder; $folder = $folder->getParent()) {
            array_unshift($names, $folder->getName());
        }

        return implode(' / ', $names);
    }
}
