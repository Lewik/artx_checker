<?php


namespace Lewik\ArtxCheckBundle\Command;


use Lewik\ArtxCheckBundle\News;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

//use Symfony\Component\Console\Input\InputArgument;
//use Symfony\Component\Console\Input\InputOption;

/** Class ArtxCheckCommand */
class ArtxCheckCommand extends ContainerAwareCommand
{
    /** @var array */
    protected $html = [];
    /** @var News[] */
    protected $newsFromSite = [];
    /** @var News[] */
    protected $freshNews = [];

    /** @var  \Swift_Mailer */
    protected $mailer;
    /** @var News[] */
    protected $newsInBase;
    /** @var string */
    protected $baseFile = 'base.serialised';


    /** @var  array */
    protected $phone;
    /** @var  array */
    protected $email;

    /** @param array $email */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /** @return array */
    public function getEmail()
    {
        return $this->email;
    }

    /** @param array $phone */
    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    /** @return array */
    public function getPhone()
    {
        return $this->phone;
    }


    /**
     * @param \Swift_Mailer $mailer
     * @param $phones
     * @param $emails
     */
    public function __construct(\Swift_Mailer $mailer, $emails, $phones)
    {
        parent::__construct();
        $this->setMailer($mailer);
        $this->setEmail($emails);
        $this->setPhone($phones);
    }



    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('lewik:artx_check')
            ->setDescription('checks artx news')
//            ->addArgument('name', InputArgument::OPTIONAL, 'Who do you want to greet?')
            ->addOption('test', '-t', InputOption::VALUE_REQUIRED, 'test news to number. example 92612345678 (no 8, no 7, no +7)')
        ;
    }

    /** @var  OutputInterface */
    protected $output;
    /** @var  InputInterface */
    protected $input;

    /** @param \Symfony\Component\Console\Output\OutputInterface $output */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /** @return \Symfony\Component\Console\Output\OutputInterface */
    public function getOutput()
    {
        return $this->output;
    }

    /** @param \Symfony\Component\Console\Input\InputInterface $input */
    public function setInput($input)
    {
        $this->input = $input;
    }

    /** @return \Symfony\Component\Console\Input\InputInterface */
    public function getInput()
    {
        return $this->input;
    }




    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInput($input);
        $this->setOutput($output);
        $this
            ->fetchSiteNewsHtml()
            ->parseNewsHtml()
            ->loadBase()
            ->selectFreshNews()
            ->saveFreshNews()
            ->sendFreshNews();

        return true;
    }

    /**
     * @return $this
     */
    protected function fetchSiteNewsHtml()
    {

        //$this->setHtml([file_get_contents('base.test')]);
        //return $this;

        $date     = new \DateTime();
        //$this->getOutput()->writeln('!!!!!!  DEV DATE WAS SETTED');
        $nowMonth = (int) $date->format('m');
        $nowYear  = $date->format('Y');
        $date = $date->modify('-1 month');
        $prevMonth   = (int) $date->format('m');
        $prevYear    = $date->format('Y');
        $html        = [];
        $urls = [
            'https://service.mylan.ru/r.php?action=news&yr=' . $prevYear . '&mn=' . $prevMonth,
            'https://service.mylan.ru/r.php?action=news&yr=' . $nowYear . '&mn=' . $nowMonth
        ];
        foreach ($urls as $url) {
            $ch = curl_init($url);
            //curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, '1');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            //curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept-Encoding:gzip,deflate,sdch"]);
            $encodedHtml = curl_exec($ch);
            curl_close($ch);
            $html[]      = iconv('KOI8-R', 'utf-8', $encodedHtml);
        }
        $this->setHtml($html);

        return $this;
    }

    /** @return $this */
    protected function parseNewsHtml()
    {
        foreach ($this->getHtml() as $html) {
            $crawler  = new Crawler();
            $crawler->addHtmlContent($html);
            $trs = $crawler->filter('.article')->filter('tr');
            $total = $trs->count();
            for ($i = 0; $i<$total;$i++){
                $news = new News();
                $date = new \DateTime();
                list($d,$m,$y) = explode('.',$trs->eq($i)->filter('font')->text());
                $date->setDate($d,$m,$y);
                $news->setDate($date);
                $news->setText($trs->eq($i)->filter('p')->text());
                $this->newsFromSite[] = $news;
            };
        }
        return $this;
    }

    /** */
    protected function loadBase()
    {
        $this->setNewsInBase([]);
        if (is_file($this->getBaseFile())) {
            $baseContent = file_get_contents($this->getBaseFile());
            if($baseContent){
                $this->setNewsInBase(unserialize($baseContent));
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function selectFreshNews()
    {
        $freshNews = [];
        $knownByHash = [];
        foreach ($this->getNewsInBase() as $news) {
            $knownByHash[$news->getHash()] = $news;
        }

        foreach ($this->getNewsFromSite() as $newsCandidate) {
            if(!array_key_exists($newsCandidate->getHash(),$knownByHash)){
                $freshNews[] = $newsCandidate;
            }
        }
        $this->setFreshNews($freshNews);
        return $this;
    }

    /** @return $this */
    protected function saveFreshNews()
    {
        $base = $this->getNewsInBase();
        foreach ($this->getFreshNews() as $freshNews) {
            $base[] = $freshNews;
        }
        file_put_contents($this->getBaseFile(), serialize($base));

        return $this;
    }

    /**
     * @return $this
     */
    protected function sendFreshNews()
    {
        foreach ($this->getFreshNews() as $freshNews) {
            foreach ($this->getPhone() as $number) {
                $this->sendSms($freshNews,$number);
            }
            foreach ($this->getEmail() as $email) {
                $this->sendEmail($freshNews,$email);
            }
        }

        return $this;
    }





    /** @param \Lewik\ArtxCheckBundle\News[] $newsInBase */
    public function setNewsInBase($newsInBase)
    {
        $this->newsInBase = $newsInBase;
    }

    /** @return \Lewik\ArtxCheckBundle\News[] */
    public function getNewsInBase()
    {
        return $this->newsInBase;
    }


    /**
     * @param News $freshNews
     * @param $number
     */
    protected function sendSms(News $freshNews,$number)
    {
        $ch = curl_init("http://sms.ru/sms/send");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [

                "api_id" => "f403578b-de71-ca54-5d71-fc0ae10fab52",
                "to"     => $number,
                "text"   => $freshNews->getText()

            ]
        );
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @param News $freshNews
     * @param $email
     */
    protected function sendEmail(News $freshNews,$email)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Уведомление о новостях artx')
            ->setContentType('text/html')
            ->setFrom('lllewik@gmail.com')
            ->setTo($email)
            ->setBody($freshNews->getText());
        $this->getMailer()->send($message);
    }













    /** @return \Swift_Mailer */
    public function getMailer()
    {
        return $this->mailer;
    }

    /** @param \Swift_Mailer $mailer */
    public function setMailer($mailer)
    {
        $this->mailer = $mailer;
    }

    /** @param \Lewik\ArtxCheckBundle\News[] $newsFromSite */
    public function setNewsFromSite($newsFromSite)
    {
        $this->newsFromSite = $newsFromSite;
    }

    /** @return \Lewik\ArtxCheckBundle\News[] */
    public function getNewsFromSite()
    {
        return $this->newsFromSite;
    }



    /** @return string */
    public function getBaseFile()
    {
        return $this->baseFile;
    }

    /** @param string $baseFile */
    public function setBaseFile($baseFile)
    {
        $this->baseFile = $baseFile;
    }

    /** @return array */
    public function getHtml()
    {
        return $this->html;
    }

    /** @param array $html */
    public function setHtml($html)
    {
        $this->html = $html;
    }

    /** @return \Lewik\ArtxCheckBundle\News[] */
    public function getFreshNews()
    {
        return $this->freshNews;
    }

    /** @param \Lewik\ArtxCheckBundle\News[] $freshFromSite */
    public function setFreshNews($freshFromSite)
    {
        $this->freshNews = $freshFromSite;
    }


}