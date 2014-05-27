<?php


namespace Lewik\ArtxCheckBundle;


/** Class News */
/** Class News */
class News
{

    /** @var  \DateTime */
    protected $date;
    /** @var  string */
    protected $title;
    /** @var  string */
    protected $text;

    /** @return \DateTime */
    public function getDate()
    {
        return $this->date;
    }

    /** @param \DateTime $date */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /** @return string */
    public function getText()
    {
        return $this->text;
    }

    /** @param string $text */
    public function setText($text)
    {
        $this->text = $text;
    }

    /** @return string */
    public function getTitle()
    {
        return $this->title;
    }

    /** @param string $title */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /** @return string */
    public function getHash(){
        return md5($this->getDate()->getTimestamp().'|'.$this->getTitle().'|'.$this->getText());
    }


}