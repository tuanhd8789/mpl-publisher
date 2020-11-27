<?php

namespace MPL\Publisher;

class AudiobookPublisher extends PremiumPublisher implements IPublisher
{
    private $title;

    private $language;

    private $content;

    public function setIdentifier($id)
    {
        //
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setAuthor($authorName)
    {
        //
    }

    public function setPublisher($publisherName)
    {
        //
    }

    public function setCoverImage($fileName, $imageData)
    {
        //
    }

    public function setTheme($theme, $contentCSS)
    {
        //
    }

    public function setDescription($description)
    {
        //
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function setDate($date)
    {
        //
    }

    public function setRights($rightsText)
    {
        //
    }

    public function addChapter($id, $title, $content)
    {
        $this->content .= "{$id}. {$title}. {$content}. ";
    }

    public function send($filename)
    {
        return $this->request('audiobook', $filename . '.mp3', [
            'language' => $this->language,
            'title' => $this->title,
            'content' => $this->content
        ]);
    }

}