<?php

/*
 * This file is part of Gitlib.
 *
 * Copyright (C) 2015-2016 The Gitamin Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gitlib\Model;

use Gitlib\Repository;

class Tree extends Object implements \RecursiveIterator
{
    protected $mode;
    protected $name;
    protected $data;
    protected $position = 0;
    protected $isInitialized = false;

    public function __construct($hash, Repository $repository)
    {
        $this->setHash($hash);
        $this->setRepository($repository);
    }

    public function initialize()
    {
        if (true === $this->isInitialized) {
            return;
        }

        $data = $this->getRepository()->getClient()->run($this->getRepository(), 'ls-tree -lz '.$this->getHash());
        $lines = explode("\0", $data);
        $files = $root = [];

        foreach ($lines as $key => $line) {
            if (empty($line)) {
                unset($lines[$key]);
                continue;
            }

            $files[] = preg_split("/[\s]+/", $line, 5);
        }

        foreach ($files as $file) {
            if ($file[1] == 'commit') {
                // submodule
                continue;
            }

            if ($file[0] == '120000') {
                $show = $this->getRepository()->getClient()->run($this->getRepository(), 'show '.$file[2]);
                $tree = new Symlink();
                $tree->setMode($file[0]);
                $tree->setName($file[4]);
                $tree->setPath($show);
                $root[] = $tree;
                continue;
            }

            if ($file[1] == 'blob') {
                $blob = new Blob($file[2], $this->getRepository());
                $blob->setMode($file[0]);
                $blob->setName($file[4]);
                $blob->setSize($file[3]);
                $root[] = $blob;
                continue;
            }

            $tree = new self($file[2], $this->getRepository());
            $tree->setMode($file[0]);
            $tree->setName($file[4]);
            $root[] = $tree;
        }

        $this->data = $root;
        $this->isInitialized = true;
    }

    public function getEntries()
    {
        $folders = $files = [];

        foreach ($this as $node) {
            $entry['name'] = $node->getName();
            $entry['mode'] = $node->getMode();

            if ($node instanceof Blob) {
                $entry['type'] = 'blob';
                $entry['size'] = $node->getSize();
                $entry['hash'] = $node->getHash();
                $files[] = $entry;
            } elseif ($node instanceof self) {
                $entry['type'] = 'folder';
                $entry['size'] = '';
                $entry['hash'] = $node->getHash();
                $folders[] = $entry;
            } elseif ($node instanceof Symlink) {
                $entry['type'] = 'symlink';
                $entry['size'] = '';
                $entry['hash'] = '';
                $entry['path'] = $node->getPath();
                $folders[] = $entry;
            }
        }

        // Little hack to make folders appear before files
        return array_merge($folders, $files);
    }

    public function valid()
    {
        return isset($this->data[$this->position]);
    }

    public function hasChildren()
    {
        return is_array($this->data[$this->position]);
    }

    public function next()
    {
        $this->position++;
    }

    public function current()
    {
        return $this->data[$this->position];
    }

    public function getChildren()
    {
        return $this->data[$this->position];
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function key()
    {
        return $this->position;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function isTree()
    {
        return true;
    }
}
