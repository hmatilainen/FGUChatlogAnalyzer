<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form type for uploading chatlog files, with HTML file restrictions.
 *
 * @package App\Form
 */
class ChatlogFileType extends FileType
{
    /**
     * Configure the options for this form type.
     *
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        
        // Override the default mime types to be more permissive
        $resolver->setDefaults([
            'mime_types' => [
                'text/html',
                'application/xhtml+xml',
                'text/plain',
                'text/ascii',
                'application/octet-stream',
            ],
        ]);
    }

    /**
     * Build the form view, restricting accepted file types to .html.
     *
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     * @return void
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        
        // Override the accept attribute to only allow .html files
        $view->vars['attr']['accept'] = '.html';
    }
} 