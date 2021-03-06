<?php
require_once dirname(__FILE__) . '/../../lib/XSIndex.class.php';

/**
 * Test class for XSIndex
 * Generated by PHPUnit on 2011-09-15 at 19:29:49.
 */
class XSIndexTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var XSIndex
	 */
	protected $object;
	protected static $data, $data_gbk;

	public static function setUpBeforeClass()
	{
		self::$data = array(
			'pid' => 1234,
			'subject' => "Hello, 测试标题",
			'message' => "您好，这儿是真正的测试内容\n另起一行用英文\n\nHello, the world!",
			'chrono' => time(),
		);
		self::$data_gbk = XS::convert(self::$data, 'GBK', 'UTF-8');
	}

	public static function tearDownAfterClass()
	{
		
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$xs = new XS(end($GLOBALS['fixIniData']));
		$this->object = $xs->index;
		$this->object->clean();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		$this->object->clean();
		$this->object->xs = null;
		$this->object = null;
	}

	public function testClean()
	{
		$search = $this->object->xs->search;
		//$this->setExpectedException('XSException', '', XS_CMD_ERR_NODB);
		$this->assertEquals(0, $search->getDbTotal());
	}

	public function testChange()
	{
		$search = $this->object->xs->search;
		// without primary key
		try {
			$e = null;
			$doc = new XSDocument;
			$this->object->add($doc);
		} catch (XSException $e) {
			
		}
		$this->assertInstanceOf('XSException', $e);
		$this->assertEquals('Missing value of primary key (FIELD:pid)', $e->getMessage());

		// Adding use default charset		
		$doc = new XSDocument(self::$data_gbk);
		$this->object->add($doc);

		// Adding use utf8 charset
		$doc = new XSDocument(self::$data, 'utf-8');
		$this->object->add($doc);
		$this->object->flushIndex();
		sleep(3);

		// test result
		$search->setCharset('utf-8');
		$this->assertEquals(2, $search->dbTotal);
		$this->assertEquals(2, $search->count('pid:1234'));
		$this->assertEquals(2, $search->count('subject:测试标题'));

		// test update
		$this->assertTrue($this->object->flushIndex()); // nothing to flush
		$doc->subject = 'none empty';
		$this->object->update($doc);
		$this->assertTrue($this->object->flushIndex()); // flushing
		$this->assertFalse($this->object->flushIndex()); // busy (false)
		sleep(2);
		$this->assertEquals(1, $search->reopen(true)->dbTotal);
		$this->assertEquals(1, $search->count('pid:1234'));
		$this->assertEquals(0, $search->count('subject:测试标题'));
		$this->assertEquals(1, $search->count('subject:none'));

		// test del by pid
		$doc->pid = 567;
		$this->object->add($doc);
		$doc->pid = 890;
		$this->object->add($doc);
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(3, $search->reopen(true)->dbTotal);

		// del by pk
		$this->object->del(567);
		$this->object->del(array('1234'), 'pid');
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(1, $search->reopen(true)->dbTotal);
		$this->assertEquals(0, $search->count('pid:1234'));
		$this->assertEquals(1, $search->count('pid:890'));
	}

	public function testRebuild()
	{
		$search = $this->object->xs->search;
		$doc = new XSDocument(self::$data_gbk);
		$this->object->add($doc);
		$this->object->add($doc);
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(2, $search->reopen(true)->dbTotal);

		$this->object->beginRebuild();
		$this->object->add($doc);
		$this->assertEquals(2, $search->reopen(true)->dbTotal);
		$this->object->endRebuild();
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(1, $search->reopen(true)->dbTotal);
	}

	public function testRebuild2()
	{
		$search = $this->object->xs->search;
		$doc = new XSDocument(self::$data_gbk);
		$this->object->add($doc);
		$this->object->add($doc);
		$this->object->flushIndex();
		sleep(3);
		$this->assertEquals(2, $search->reopen(true)->dbTotal);

		$this->object->beginRebuild();
		$this->object->add($doc);
		$this->object->add($doc);
		$this->assertEquals(2, $search->reopen(true)->dbTotal);
		$e = null;
		try {
			$this->object->beginRebuild();
		} catch (XSException $e) {
			
		}
		$this->assertNotNull($e);
		$this->assertEquals(XS_CMD_ERR_REBUILDING, $e->getCode());
		$this->object->add($doc);
		$this->assertEquals(2, $search->reopen(true)->dbTotal);
		$this->object->endRebuild();
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(3, $search->reopen(true)->dbTotal);

		$this->object->beginRebuild();
		$this->object->add($doc);
		$this->object->add($doc);
		$this->object->endRebuild();
		$this->object->stopRebuild();
		$this->object->flushIndex();
		sleep(2);
		$this->assertEquals(3, $search->reopen(true)->dbTotal);
	}

	public function testSynonyms($buffer = false)
	{
		$index = $this->object;
		$search = $this->object->xs->search;

		// simple add synonyms
		if ($buffer) {
			$index->openBuffer();
		}
		$index->addSynonym('foo', 'bar');
		$index->addSynonym('FOO', 'Bra');
		$index->addSynonym('Hello World', 'hi');
		$index->addSynonym('检索', '搜索');
		$index->addSynonym('search', '搜索');
		if ($buffer) {
			$index->closeBuffer();
		}
		$index->flushIndex();
		sleep(4);

		$synonyms = $search->reopen(true)->getAllSynonyms(0, 0, true);
		$this->assertArrayNotHasKey('FOO', $synonyms);
		$this->assertArrayNotHasKey('Zhello world', $synonyms);
		$this->assertArrayNotHasKey('Z检索', $synonyms);
		$this->assertEquals('bar bra', implode(' ', $synonyms['foo']));
		$this->assertEquals('Zbar Zbra', implode(' ', $synonyms['Zfoo']));
		$this->assertEquals('hi', implode(' ', $synonyms['hello world']));
		$this->assertEquals('搜索', implode(' ', $synonyms['检索']));
		$this->assertEquals('搜索', implode(' ', $synonyms['Zsearch']));
		$this->assertEquals('搜索', implode(' ', $synonyms['search']));

		// simple del synonyms
		if ($buffer) {
			$index->openBuffer();
		}
		$index->delSynonym('FOO', 'Bra');
		$index->delSynonym('Hello World');
		$index->delSynonym('检索', '搜索');
		if ($buffer) {
			$index->closeBuffer();
		}
		$index->flushIndex();
		sleep(2);

		$synonyms = $search->reopen(true)->getAllSynonyms(0, 0, true);
		$this->assertArrayNotHasKey('检索', $synonyms);
		$this->assertArrayNotHasKey('hello world', $synonyms);
		$this->assertEquals('bar', implode(' ', $synonyms['foo']));
		$this->assertEquals('Zbar', implode(' ', $synonyms['Zfoo']));
		$this->assertEquals('搜索', implode(' ', $synonyms['Zsearch']));
		$this->assertEquals('搜索', implode(' ', $synonyms['search']));
	}

	public function testSynonyms2()
	{
		$this->testSynonyms(true);
	}

	public function testCustomDict()
	{
		$index = $this->object;
		$index->setCustomDict('');
		$this->assertEmpty($index->getCustomDict());
		$dict = <<<EOF
搜一下	1.0		1.1		vn
测测看	2.0		2.1		vn
EOF;
		$index->setCustomDict($dict);
		$this->assertEquals($dict, $index->getCustomDict());

		// add document
		$doc = new XSDocument(self::$data, 'utf-8');
		$doc->subject = '去测测看';
		$this->object->add($doc);
		$this->object->flushIndex();
		sleep(3);
		$search = $this->object->xs->search;
		$search->reopen(true);
		$search->setCharset('utf-8');
		$this->assertEquals(1, $search->count('subject:测测看'));
		$this->assertEquals(1, $search->count('subject:测看'));
		$this->assertEquals(0, $search->count('subject:看'));
	}

	private function countSubjectTerm($term)
	{
		$search = $this->object->xs->search->reopen(true)->setCharset('utf-8');
		return $search->setQuery(null)->addQueryTerm('subject', $term)->count();
	}

	public function testScwsMulti()
	{
		// objects
		$index = $this->object;
		$doc = new XSDocument('utf-8');
		$doc->pid = 7788;
		$doc->subject = '管理制度';
		$doc->message = '中华人民共和国';
		// default scws
		$this->assertEquals(3, $index->getScwsMulti());
		$index->setScwsMulti(16);
		$this->assertEquals(3, $index->getScwsMulti());
		$index->setScwsMulti(-1);
		$this->assertEquals(3, $index->getScwsMulti());
		$index->update($doc);
		$index->flushIndex();
		sleep(2);
		$this->assertEquals(1, $this->countSubjectTerm('管理制度'));
		$this->assertEquals(1, $this->countSubjectTerm('管理'));
		$this->assertEquals(0, $this->countSubjectTerm('管'));
		$this->assertEquals(0, $this->countSubjectTerm('制'));
		// multi = 0
		$index->setScwsMulti(0);
		$index->update($doc);
		$index->flushIndex();
		sleep(2);
		$this->assertEquals(1, $this->countSubjectTerm('管理制度'));
		$this->assertEquals(0, $this->countSubjectTerm('管理'));
		$this->assertEquals(0, $this->countSubjectTerm('管'));
		$this->assertEquals(0, $this->countSubjectTerm('制'));
		// multi = 5
		$index->setScwsMulti(5);
		$index->update($doc);
		$index->flushIndex();
		sleep(2);
		$this->assertEquals(1, $this->countSubjectTerm('管理制度'));
		$this->assertEquals(1, $this->countSubjectTerm('管理'));
		$this->assertEquals(1, $this->countSubjectTerm('管'));
		$this->assertEquals(0, $this->countSubjectTerm('制'));
		// multi = 15
		$index->setScwsMulti(15);
		$index->update($doc);
		$index->flushIndex();
		sleep(2);
		$this->assertEquals(1, $this->countSubjectTerm('管理制度'));
		$this->assertEquals(1, $this->countSubjectTerm('管理'));
		$this->assertEquals(1, $this->countSubjectTerm('管'));
		$this->assertEquals(1, $this->countSubjectTerm('制'));
	}
}
