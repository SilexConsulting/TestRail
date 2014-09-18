<?php

require_once 'testrail-api/php/testrail.php';

/**
 * Server  HAS MANY Projects
 * Project HAS MANY Milestones
 * Project HAS MANY Suites
 * Suite   HAS MANY Sections
 * Section HAS MANY Sections
 * Section HAS MANY Cases
 */

/**
 * Operations to assist syncing TestRail cases between projects
 */
class TestRailSync extends TestRailAPIClient
{
	/**
	 * @var string $sourceProject
	 */
	private $sourceProject;

	/**
	 * @var string $destinationProject
	 */
	private $destinationProject;

	/**
	 * @var array $sourceMilestones
	 */
	private $sourceMilestones;

	/**
	 * @var array $destinationMilestones
	 */
	private $destinationMilestones;

	/**
	 * @var array $sourceMilestones
	 */
	private $sourceSuites;

	/**
	 * @var array $destinationMilestones
	 */
	private $destinationSuites;

	/**
	 * Set the source project
	 *
	 * @param int $sourceProject
	 */
	public function set_source($sourceProject)
	{
		$this->sourceProject = $sourceProject;
	}

	/**
	 * Set the destination project
	 *
	 * @param int $destinationProject
	 */
	public function set_destination($destinationProject)
	{
		$this->destinationProject = $destinationProject;
	}

	/**
	 * Set log file
	 *
	 * @param int $log
	 */
	public function set_log($log)
	{
		$this->log = $log;
	}

	public function set_delete($delete) {
		$this->delete = $delete;
	}

	/**
	 * Perform sync operation between sourceProject and destinationProject
	 */
	public function sync()
	{
		// After this call, milestones are sync'd
		// After this call, $this->sourceMilestones includes a destination_id key
		$this->syncMilestones();
		// After this call, suites are sync'd
		// After this call, $this->sourceSuites includes a destination_id key
		$this->syncSuites();

		// After this call, sections and cases are sync'd
		$this->syncSections();
	}

	/** START CASE CODE */

	/**
	 * Sync Cases from source to destination
	 *
	 * @param $sourceSuite
	 * @param $sourceSections
	 */
	private function syncCases($sourceSuite, $sourceSections)
	{
		foreach ($sourceSections as $sourceSection)
		{
			$sourceCases = $this->getCases($this->sourceProject, $sourceSuite['id'], $sourceSection['id']);
			$destinationCases = $this->getCases($this->destinationProject, $sourceSuite['destination_id'], $sourceSection['destination_id']);

			$this->deleteOrphanedCases($sourceCases, $destinationCases);
			$this->matchCases($sourceCases, $destinationCases);
			$this->copyCases($sourceCases, $sourceSuite['destination_id'], $sourceSection['destination_id']);
		}
	}

	/**
	 * Sync $sourceProject's milestones to $destinationProject
	 */
	public function copyCases(&$sourceCases, $destinationSuiteId, $destinationSectionId)
	{
		foreach ($sourceCases as &$sourceCase) {
			if (!isset($sourceCase['destination_id'])) {
				$destinationCase = $this->addCase($this->destinationProject, $destinationSuiteId, $destinationSectionId, $sourceCase);
				$sourceCase['destination_id'] = $destinationCase['id'];
			}
		}
	}

	/**
	 * Add a new section to a project
	 *
	 * @param $projectId Project to add to
	 * @param $destinationSuiteId Suite to add to
	 * @param $destinationSectionId Section to add to
	 * @param $sourceCase Case to add
	 * @return array|mixed
	 */
	private function addCase($projectId, $destinationSuiteId, $destinationSectionId, $sourceCase)
	{
		$data = array(
			'title'        => $sourceCase['title'],
			'type_id'      => $sourceCase['type_id'],
			'priority_id'  => $sourceCase['priority_id'],
			'estimate'     => $sourceCase['estimate'],
			'milestone_id' => $sourceCase['milestone_id'],
			'refs'         => $sourceCase['refs'],
		);
		$this->error_log("Add new Case '{$sourceCase['title']}' ({$sourceCase['id']})");
		return $this->send_post("add_case/{$destinationSectionId}", $data);
	}

	/**
	 * If a case exists in Destination but not in Source, delete it from Destination
	 *
	 * @param $sourceCases
	 * @param $destinationCases
	 */
	private function deleteOrphanedCases(&$sourceCases, &$destinationCases)
	{
		foreach ($destinationCases as $destinationKey => $destinationCase) {
			$found = FALSE;
			foreach ($sourceCases as $sourceCase)
			{
				if ($this->equalCases($sourceCase, $destinationCase))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteCase($destinationCase);
				unset($destinationCase[$destinationKey]);
			}
		}
	}

	/**
	 * Return an array of cases for the given projectid, suiteid and sectionId
	 *
	 * @param int $projectId
	 * @param int $suiteId
	 * @param int $sectionId
	 * @return array|mixed
	 */
	private function getCases($projectId, $suiteId, $sectionId)
	{
		$cases = $this->send_get("get_cases/{$projectId}&suite_id={$suiteId}&section_id={$sectionId}");
		$this->assertUniqueCases($cases);
		return $cases;
	}

	/**
	 * Throws an exception if two identical sections are found in $sections
	 *
	 * @param array $cases
	 * @throws Exception
	 */
	private function assertUniqueCases($cases)
	{
		for ($i = 0; $i < count($cases); $i++) {
			for ($j = 0; $j < $i; $j++) {
				if ($this->equalCases($cases[$i], $cases[$j])) {
					throw new Exception("Found two identical cases (id:{$sections[$i]['id']}, id:{$sections[$j]['id']}) cannot continue");
				}
			}
		}
	}

	/**
	 * Returns true if the cases can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the cases can be considered equal, else false
	 */
	private function equalCases($a, $b)
	{
		if ($a['title'] != $b['title']) {
			return false;
		}
		return true;
	}

	/**
	 * Delete a case
	 *
	 * @param array $case
	 */
	private function deleteCase($case)
	{
		$this->error_log("Delete orphaned Case '{$case['title']}' ({$case['id']})");
		if ($this->delete) {
			$this->send_post("delete_case/{$case['id']}", array());
		}
	}

	/**
	 * If a case is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceCase with it's matching destinationCase's ID
	 */
	private function matchCases(&$sourceCases, &$destinationCases)
	{
		foreach ($sourceCases as &$sourceCase) {
			foreach ($destinationCases as $destinationCase) {
				if ($this->equalCases($sourceCase, $destinationCase)) {
					$sourceCase['destination_id'] = $destinationCase['id'];
				}
			}
		}
	}

	/** END CASE CODE */

	/** START SECTION CODE */

	/**
	 * Sync Sections from Source to Destination.
	 */
	private function syncSections()
	{
		foreach ($this->sourceSuites as $sourceSuite)
		{
			$sourceSections = $this->getSections($this->sourceProject, $sourceSuite['id']);
			$destinationSections = $this->getSections($this->destinationProject, $sourceSuite['destination_id']);

			$this->deleteOrphanedSections($sourceSections, $destinationSections);
			$this->matchSections($sourceSections, $destinationSections);
			$this->copySections($sourceSections, $sourceSuite);

			$this->syncCases($sourceSuite, $sourceSections);
		}
	}

	/**
	 * If a section exists in Destination but not in Source, delete it from Destination
	 *
	 * @param $sourceSections
	 * @param $destinationSections
	 */
	private function deleteOrphanedSections(&$sourceSections, &$destinationSections)
	{
		foreach ($destinationSections as $destinationKey => $destinationSection) {
			$found = FALSE;
			foreach ($sourceSections as $sourceSection)
			{
				if ($this->equalSections($sourceSection, $destinationSection))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteSection($destinationSection);
				unset($destinationSection[$destinationKey]);
			}
		}
	}

	/**
	 * If a section is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceSection with its matching destinationSection's ID
	 */
	private function matchSections(&$sourceSections, &$destinationSections)
	{
		foreach ($sourceSections as &$sourceSection) {
			foreach ($destinationSections as $destinationSection) {
				if ($this->equalSections($sourceSection, $destinationSection)) {
					$sourceSection['destination_id'] = $destinationSection['id'];
				}
			}
		}
	}

	/**
	 * Return an array of sections for the given projectid and suiteid
	 *
	 * @param int $projectId
	 * @param int $suiteId
	 * @return array|mixed
	 */
	private function getSections($projectId, $suiteId)
	{
		$sections = $this->send_get("get_sections/{$projectId}&suite_id={$suiteId}");
		$this->assertUniqueSections($sections);
		return $sections;
	}

	/**
	 * Throws an exception if two identical sections are found in $sections
	 *
	 * @param array $sections
	 * @throws Exception
	 */
	private function assertUniqueSections($sections)
	{
		for ($i = 0; $i < count($sections); $i++) {
			for ($j = 0; $j < $i; $j++) {
				if ($this->equalSections($sections[$i], $sections[$j])) {
					throw new Exception("Found two identical sections (id:{$sections[$i]['id']}, id:{$sections[$j]['id']}) cannot continue");
				}
			}
		}
	}

	/**
	 * Sync $sourceProject's sections to $destinationProject
	 */
	public function copySections(&$sourceSections, $sourceSuite)
	{
		foreach ($sourceSections as &$sourceSection) {
			if (!isset($sourceSection['destination_id'])) {
				$destinationSection = $this->addSection($this->destinationProject, $sourceSuite, $sourceSection);
				$sourceSection['destination_id'] = $destinationSection['id'];
			}
		}
	}

	/**
	 * Returns true if the sections can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the sections can be considered equal, else false
	 */
	private function equalSections($a, $b)
	{
		if ($a['name'] != $b['name']) {
			return false;
		}
		return true;
	}

	/**
	 * Delete a section
	 *
	 * @param array $section
	 */
	private function deleteSection($section)
	{
		$this->error_log("Delete orphaned Section '{$section['name']}' ({$section['id']})");
		if ($this->delete) {
			$this->send_post("delete_section/{$section['id']}", array());
		}
	}

	/**
	 * Add a new section to a project
	 *
	 * @param $projectId Project to add to
	 * @param $sourceSuite Source suite
	 * @param $sourceSection Source section
	 * @return array|mixed
	 */
	private function addSection($projectId, $sourceSuite, $sourceSection)
	{
		$data = array(
			'suite_id'      => $sourceSuite['destination_id'],
			'name'          => $sourceSection['name'],
			// parent_id is missing, which is why sections are getting flattened out.
		);
		$this->error_log("Add new Section '{$sourceSection['name']}'");
		return $this->send_post("add_section/{$projectId}", $data);
	}

	/** END SECTION CODE */

	/** START SUITES CODE */

	/**
	 * Sync Suites from Source to Destination.
	 */
	private function syncSuites()
	{
		$this->sourceSuites = $this->getSuites($this->sourceProject);
		$this->destinationSuites = $this->getSuites($this->destinationProject);

		$this->deleteOrphanedSuites();
		$this->matchSuites();
		$this->copySuites();
	}

	/**
	 * If a suite exists in Destination but not in Source, delete it from Destination
	 */
	private function deleteOrphanedSuites()
	{
		foreach ($this->destinationSuites as $destinationKey => $destinationSuite) {
			$found = FALSE;
			foreach ($this->sourceSuites as $sourceSuite)
			{
				if ($this->equalSuites($sourceSuite, $destinationSuite))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteSuite($destinationSuite);
				unset($this->destinationSuite[$destinationKey]);
			}
		}
	}

	/**
	 * If a suite is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceSuite with it's matching destinationSuite's ID
	 */
	private function matchSuites()
	{
		foreach ($this->sourceSuites as &$sourceSuite) {
			foreach ($this->destinationSuites as $destinationSuite) {
				if ($this->equalSuites($sourceSuite, $destinationSuite)) {
					$sourceSuite['destination_id'] = $destinationSuite['id'];
				}
			}
		}
	}

	/**
	 * Sync $sourceProject's suites to $destinationProject
	 */
	public function copySuites()
	{
		foreach ($this->sourceSuites as &$sourceSuite) {
			if (!isset($sourceSuite['destination_id'])) {
				$destinationSuite = $this->addSuite($this->destinationProject, $sourceSuite);
				$sourceSuite['destination_id'] = $destinationSuite['id'];
			}
		}
	}

	/**
	 * Return an array of suites for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getSuites($projectId)
	{
		$suites = $this->send_get("get_suites/{$projectId}");
		$this->assertUniqueSuites($suites);
		return $suites;
	}

	/**
	 * Throws an exception if two identical suites are found in $suites
	 *
	 * @param array $suites
	 * @throws Exception
	 */
	private function assertUniqueSuites($suites)
	{
		for ($i = 0; $i < count($suites); $i++) {
			for ($j = 0; $j < $i; $j++) {
				if ($this->equalSuites($suites[$i], $suites[$j])) {
					throw new Exception("Found two identical suites (id:{$suites[$i]['id']}, id:{$suites[$j]['id']}) cannot continue");
				}
			}
		}
	}

	/**
	 * Delete a suite
	 *
	 * @param array $suite
	 */
	private function deleteSuite($suite)
	{
		$this->error_log("Delete orphaned Suite '{$suite['name']}' ({$suite['id']})");
		if ($this->delete) {
			$this->send_post("delete_suite/{$suite['id']}", array());
		}
	}

	/**
	 * Returns true if the suites can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the suites can be considered equal, else false
	 */
	private function equalSuites($a, $b)
	{
		if ($a['name'] != $b['name']) {
			return false;
		}
		return true;
	}

	/**
	 * Add a new suite to a project
	 *
	 * @param $projectId Project to add to
	 * @param $suite Suite to add
	 * @return array|mixed
	 */
	private function addSuite($projectId, $suite)
	{
		$data = array(
			'name'          => $suite['name'],
			'description'   => $suite['description'],
		);
		$this->error_log("Add new Suite '{$suite['name']}'");
		return $this->send_post("add_suite/{$projectId}", $data);
	}

	/** END SUITES CODE */

	/** START MILESTONES CODE */

	/**
	 * Sync milestones from Source to Destination.
	 */
	private function syncMilestones()
	{
		$this->sourceMilestones = $this->getMilestones($this->sourceProject);
		$this->destinationMilestones = $this->getMilestones($this->destinationProject);

		$this->deleteOrphanedMilestones();
		$this->matchMilestones();
		$this->copyMilestones();
	}

	/**
	 * If a milestone exists in Destination but not in Source, delete it from Destination
	 */
	private function deleteOrphanedMilestones()
	{
		foreach ($this->destinationMilestones as $destinationKey => $destinationMilestone) {
			$found = FALSE;
			foreach ($this->sourceMilestones as $sourceMilestone)
			{
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteMilestone($destinationMilestone);
				unset($this->destinationMilestones[$destinationKey]);
			}
		}
	}

	/**
	 * If a milestone is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceMilestone with it's matching destinationMilestone's ID
	 */
	private function matchMilestones()
	{
		foreach ($this->sourceMilestones as &$sourceMilestone) {
			foreach ($this->destinationMilestones as $destinationMilestone) {
				if ($this->equalMilestones($sourceMilestone, $destinationMilestone)) {
					$sourceMilestone['destination_id'] = $destinationMilestone['id'];
				}
			}
		}
	}

	/**
	 * Sync $sourceProject's milestones to $destinationProject
	 */
	public function copyMilestones()
	{
		foreach ($this->sourceMilestones as &$sourceMilestone) {
			if (!isset($sourceMilestone['destination_id'])) {
				$destinationMilestone = $this->addMilestone($this->destinationProject, $sourceMilestone);
				$sourceMilestone['destination_id'] = $destinationMilestone['id'];
			}
		}
	}

	/**
	 * Return an array of milestones for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getMilestones($projectId)
	{
		$milestones = $this->send_get("get_milestones/{$projectId}");
		$this->assertUniqueMilestones($milestones);
		return $milestones;
	}

	/**
	 * Throws an exception if two identical milestones are found in $milestones
	 *
	 * @param array $milestones
	 * @throws Exception
	 */
	private function assertUniqueMilestones($milestones)
	{
		for ($i = 0; $i < count($milestones); $i++) {
			for ($j = 0; $j < $i; $j++) {
				if ($this->equalMilestones($milestones[$i], $milestones[$j])) {
					throw new Exception("Found two identical milestones (id:{$milestones[$i]['id']}, id:{$milestones[$j]['id']}) cannot continue");
				}
			}
		}
	}


	/**
	 * Returns true if the milestones can be considered equal, else false
	 *
	 * @param array $a
	 * @param array $b
	 * @return bool true if the milestones can be considered equal, else false
	 */
	private function equalMilestones($a, $b)
	{
		if ($a['name'] != $b['name']) {
			return false;
		}
		return true;
	}

	/**
	 * Delete a milestone
	 *
	 * @param array $milestone
	 */
	private function deleteMilestone($milestone)
	{
		$this->error_log("Delete orphaned Milestone '{$milestone['name']}' ({$milestone['id']})");
		if ($this->delete) {
			$this->send_post("delete_milestone/{$milestone['id']}", array());
		}
	}

	/**
	 * Add a new milestone to a project
	 *
	 * @param $projectId Project to add to
	 * @param $milestone Milestone to add
	 * @return array|mixed
	 */
	private function addMilestone($projectId, $milestone)
	{
		$data = array(
			'name'          => $milestone['name'],
			'description'   => $milestone['description'],
			'due_on'        => $milestone['due_on'],
		);
		$this->error_log("Add new Milestone '{$milestone['name']}'");
		return $this->send_post("add_milestone/{$projectId}", $data);
	}

	/** END MILESTONES CODE */

	private function error_log($message)
	{
		error_log(date('r') . ' ' . $message . PHP_EOL, 3, $this->log);
	}

    /**
     * Test for duplicate milestones within a specified project.
     * @param $projectId
     * @type array $milestones_array
     * @return bool TRUE if there are duplicate milestones in $this->sourceProject, else FALSE
     */
    private function duplicateMilestones($projectId)
    {
      $milestones_array = $this->send_get("get_milestones/{$projectId}");

      for ($i = 0; $i < count($milestones_array); $i++) {
        for ($j = 0; $j < count($milestones_array); $j++) {
          if ($i == $j) {
            //Continue at this point to skip the processing of commutative values
            continue;
          }
          if ($this->equalMilestones($milestones_array[$i], $milestones_array[$j])) {
            return true;
          }
        }
      }
      return false;
    }
}
