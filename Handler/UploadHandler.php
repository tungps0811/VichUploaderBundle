<?php

namespace Vich\UploaderBundle\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\Event as ContractEvent;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Vich\UploaderBundle\Injector\FileInjectorInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Mapping\PropertyMappingFactory;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Upload handler.
 *
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class UploadHandler extends AbstractHandler
{
    /**
     * @var FileInjectorInterface
     */
    protected $injector;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function __construct(PropertyMappingFactory $factory, StorageInterface $storage, FileInjectorInterface $injector, EventDispatcherInterface $dispatcher)
    {
        parent::__construct($factory, $storage);

        $this->injector = $injector;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Checks for file to upload.
     *
     * @param object $obj       The object
     * @param string $fieldName The name of the field containing the upload (has to be mapped)
     *
     * @throws \Vich\UploaderBundle\Exception\MappingNotFoundException
     */
    public function upload($obj, string $fieldName): void
    {
        $mapping = $this->getMapping($obj, $fieldName);

        // nothing to upload
        if (!$this->hasUploadedFile($obj, $mapping)) {
            return;
        }

        $this->dispatch(Events::PRE_UPLOAD, new Event($obj, $mapping));

        $this->storage->upload($obj, $mapping);
        $this->injector->injectFile($obj, $mapping);

        $this->dispatch(Events::POST_UPLOAD, new Event($obj, $mapping));
    }

    public function inject($obj, string $fieldName): void
    {
        $mapping = $this->getMapping($obj, $fieldName);

        $this->dispatch(Events::PRE_INJECT, new Event($obj, $mapping));

        $this->injector->injectFile($obj, $mapping);

        $this->dispatch(Events::POST_INJECT, new Event($obj, $mapping));
    }

    public function clean($obj, string $fieldName): void
    {
        $mapping = $this->getMapping($obj, $fieldName);

        // nothing uploaded, do not remove anything
        if (!$this->hasUploadedFile($obj, $mapping)) {
            return;
        }

        $this->remove($obj, $fieldName);
    }

    public function remove($obj, string $fieldName): void
    {
        $mapping = $this->getMapping($obj, $fieldName);
        $oldFilename = $mapping->getFileName($obj);

        // nothing to remove, avoid dispatching useless events
        if (empty($oldFilename)) {
            return;
        }

        $this->dispatch(Events::PRE_REMOVE, new Event($obj, $mapping));

        $this->storage->remove($obj, $mapping);
        $mapping->erase($obj);

        $this->dispatch(Events::POST_REMOVE, new Event($obj, $mapping));
    }

    protected function dispatch(string $eventName, Event $event): void
    {
        if ('42' !== Kernel::MAJOR_VERSION.Kernel::MINOR_VERSION && class_exists(ContractEvent::class)) {
            $this->dispatcher->dispatch($event, $eventName);
        } else {
            $this->dispatcher->dispatch($eventName, $event);
        }
    }

    protected function hasUploadedFile($obj, PropertyMapping $mapping): bool
    {
        $file = $mapping->getFile($obj);

        return null !== $file && $file instanceof UploadedFile;
    }
}
