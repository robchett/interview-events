<?php

namespace App\Form\Type;

use App\Form\GetEventsRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** @extends AbstractType<GetEventsRequest> */
final class GetEventsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startFrom', DateType::class)
            ->add('startTo', DateType::class)
            ->add('endFrom', DateType::class)
            ->add('endTo', DateType::class)
            ->add('title', TextType::class)
            ->add('pageSize', IntegerType::class)
            ->add('page', IntegerType::class)
            ->setMethod('GET')
        ;
    }
}
