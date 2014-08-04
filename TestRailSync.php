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
	 * COMPLETED Set the source project
	 *
	 * @param int $sourceProject
	 */
	public function set_source($sourceProject)
	{
		$this->sourceProject = $sourceProject;
	}

	/**
	 * COMPLETED Set the destination project
	 *
	 * @param int $destinationProject
	 */
	public function set_destination($destinationProject)
	{
		$this->destinationProject = $destinationProject;
	}

	/**
	 * Perform sync operation between sourceProject and destinationProject
	 */
	public function sync()
	{
		// After this call, $this->destinationMilestones is sync'd
		// After this call, $this->sourceMilestones includes a destination_id key
		$this->syncMilestones();

		// After this call, $this->destinationSuites is sync'd
		// After this call, $this->sourceSuites includes a destination_id key
		$this->syncSuites();


		$this->deleteOrphanedSuites();
		$this->matchSuites();
		$this->copySuites();

		foreach ($this->sourceSuites as $sourceSuite)
		{
			$this->syncSection($sourceSuite);
		}
	}

	/** START SUITES CODE */

	private function syncSuites()
	{
		$this->sourceSuites = $this->getSuites($this->sourceProject);
		$this->destinationSuites = $this->getSuites($this->destinationProject);

		$this->deleteOrphanedSuites();
		$this->matchSuites();
		$this->copyMilestones();
	}

	/**
	 * COMPLETED If a suite exists in Destination but not in Source, delete it from Destination
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
	 * COMPLETED Return an array of suites for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getSuites($projectId)
	{
		return $this->send_get("get_suites/{$projectId}");
	}

	/**
	 * COMPLETED Delete a suite
	 *
	 * @param array $suite
	 */
	private function deleteSuite($suite)
	{
		$this->send_post("delete_suite/{$suite['id']}", array());
	}

	/**
	 * COMPLETED Returns true if the suites can be considered equal, else false
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







	/** END SUITES CODE */

	/** START MILESTONES CODE */

	/**
	 * COMPLETED Sync milestones from Source to Destination.
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
	 * COMPLETED If a milestone exists in Destination but not in Source, delete it from Destination
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
	 * COMPLETED If a milestone is identical in Source and Destination, remove it from consideration
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
	 * COMPLETED Sync $sourceProject's milestones to $destinationProject
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
	 * COMPLETED Return an array of milestones for the given projectid
	 *
	 * @param int projectid Project to get from
	 * @return array|mixed
	 */
	private function getMilestones($projectId)
	{
		return $this->send_get("get_milestones/{$projectId}");
	}

	/**
	 * COMPLETED Returns true if the milestones can be considered equal, else false
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
	 * COMPLETED Delete a milestone
	 *
	 * @param array $milestone
	 */
	private function deleteMilestone($milestone)
	{
		$this->send_post("delete_milestone/{$milestone['id']}", array());
	}

	/** END MILESTONES CODE */







	public function syncSection($sourceSuite)
	{
		$this->sourceSections = $this->getSections($this->sourceProject, $sourceSuite['id']);
		$this->destinationSections = $this->getSections($this->destinationProject, $sourceSuite['destination_id']);

		$this->deleteOrphanedSections();
		$this->matchSections();
		$this->copySections();
	}

	/**
	 * If a section exists in Destination but not in Source, delete it from Destination
	 */
	private function deleteOrphanedSections()
	{
		foreach ($this->destinationSections as $destinationKey => $destinationSection) {
			$found = FALSE;
			foreach ($this->sourceSection as $sourceSection)
			{
				if ($this->equalSections($sourceSection, $destinationSection))
				{
					$found = TRUE;
					break;
				}
			}
			if ($found == FALSE) {
				$this->deleteSection($destinationSection);
				unset($this->destinationSection[$destinationKey]);
			}
		}
	}

	/**
	 * If a section is identical in Source and Destination, remove it from consideration
	 * by tagging the sourceSection with it's matching destinationSection's ID
	 */
	private function matchSections()
	{
		foreach ($this->sourceSections as &$sourceSection) {
			foreach ($this->destinationSections as $destinationSection) {
				if ($this->equalSections($sourceSection, $destinationSection)) {
					$sourceSection['destination_id'] = $destinationSection['id'];
				}
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
	 * Sync $sourceProject's sections to $destinationProject
	 */
	public function copySections()
	{
		foreach ($this->sourceSections as &$sourceSection) {
			if (!isset($sourceSection['destination_id'])) {
				$destinationSection = $this->addSection($this->destinationProject, $sourceSection);
				$sourceSection['destination_id'] = $destinationSection['id'];
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
		return $this->send_post("add_milestone/{$projectId}", $data);
	}

	/**
	 * Add a new section to a project
	 *
	 * @param $projectId Project to add to
	 * @param $section Section to add
	 * @return array|mixed
	 */
	private function addSection($projectId, $section)
	{
		$data = array(
			'name'          => $section['name'],
			'suite_id'      => $section['description'],
			'parent_id'     => $section['description'],
		);
		return $this->send_post("add_section/{$projectId}", $data);
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
		return $this->send_post("add_suite/{$projectId}", $data);
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
		return $this->send_get("get_sections/{$projectId}&suite_id={$suiteId}");
	}

	/**
	 * Test for duplicate suites
	 *
	 * @return TRUE if there are duplicate suite names in $this->sourceProject, else FALSE
	 */
	private function duplicateSuite()
	{
		// Uses $this->equalSuites() method to test for equality.
	}

    /**
     * Test for duplicate milestones within a specified project.
     * @param $projectId
     * @type array $milestones_array
     * @return bool TRUE if there are duplicate milestones in $this->sourceProject, else FALSE
     */
    private function duplicateMilestones($projectId)
      {
      //Get $milestones_array for the given $projectId.
      $milestones_array = $this->send_get("get_milestones/{$projectId}");

      for ($i = 0; $i < count($milestones_array); $i++) {
        for ($j = 0; $j < count($milestones_array); $j++) {
          if ($i == $j) {
            continue;
          }
          //Uses $this->equalMilestones() method to test for equality.
          //@return TRUE if a duplicate milestone is found.
          if ($this->equalMilestones($milestones_array[$i], $milestones_array[$j])) {
            return true;
          }
        }
      }
      return false;
    }
}
