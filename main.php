<?php

interface SpecificationInterface {
	public function isSatisfiedBy(Sample $sample);
}

class OrSpecification implements SpecificationInterface {

	private $specifications;


	public function __construct($specifications)
	{
		$this->specifications = $specifications;
	}

	public function isSatisfiedBy(Sample $sample) {
		foreach ($this->specifications as $specification) {
			if ($specification->isSatisfiedBy($sample)) {
				return true;
			}
		}
		return false;
	}
}

class AndSpecification implements SpecificationInterface {

	private $specifications;


	public function __construct($specifications) {
		$this->specifications = $specifications;
	}

	public function isSatisfiedBy(Sample $sample) {
		foreach ($this->specifications as $specification) {
			if (!$specification->isSatisfiedBy($sample)) {
				return false;
			}
		}

		return true;
	}
}

class NotSpecification implements SpecificationInterface {

	private $specification;

	public function __construct(SpecificationInterface $specification) {
		$this->specification = $specification;
	}

	public function isSatisfiedBy(Sample $sample) {
		return !$this->specification->isSatisfiedBy($sample);
	}
}

class OwnerSpecification implements SpecificationInterface {

	private $_owner;

	public function __construct($owner) {
		$this->_owner = $owner;
	}

	public function isSatisfiedBy(Sample $sample) {
		return $sample->carriedBy === $this->_owner;
	}
}

class FeasibilitySpecification implements SpecificationInterface {


	public function isSatisfiedBy(Sample $sample) {

		$costs = Molecule::each(function($molecule) use ($sample) {
			return $sample->getCost($molecule, false);
		});

		return array_sum($costs) <= 10;
	}
}

class ReadySpecification implements SpecificationInterface {

	private $robot;

	public function __construct(Robot $robot) {
		$this->robot = $robot;
	}

	public function isSatisfiedBy(Sample $sample) {

		$molecules = Molecule::each(function($molecule) use ($sample) {
			return $sample->getCost($molecule) <= 0;
		});
		
		foreach ($molecules as $ready) {
			if(!$ready) return false;
		}

		$isDiagnosed = new DiagnosedSpecification();

		return $isDiagnosed->isSatisfiedBy($sample);
	}
}

class AvailabilitySpecification implements SpecificationInterface {

	public $total;

	public function __construct($total = true) {
		$this->total = $total;
	}


	public function isSatisfiedBy(Sample $sample) {

		$availabilities = Molecule::each(function($molecule) use ($sample) {
			if($this->total)
				return $sample->getCost($molecule) <= Controller::instance()->availableTotal[$molecule];
			else
				return $sample->getCost($molecule) <= Controller::instance()->available[$molecule];
		});

		foreach ($availabilities as $availability) {
			if(!$availability) return false;
		}

		return true;
	}
}


class DiagnosedSpecification implements SpecificationInterface {

	public function isSatisfiedBy(Sample $sample) {
		return $sample->health !== -1;
	}
}


interface ActionInterface {
	public function isValid();

	public function get();
}

class MoveAction implements ActionInterface {
	
	const NAME = "GOTO";
	
	public $location;

	public function __construct($location) {
		$this->location = $location;
	}

	public function isValid() {
		return $this->location !== Controller::instance()->me->target;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->location]);
	}
}

class GetSampleAction implements ActionInterface {
	
	const NAME = "CONNECT";

	public $level;

	public function __construct($level) {
		$this->level = $level;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::SAMPLES)
			return false;

		$ownByMe = new OwnerSpecification(Owner::ME);
		$hand = Controller::instance()->filter($ownByMe);

		return count($hand) < 3;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->level]);
	}
}

class DiagnosisAction implements ActionInterface {

	const NAME = "CONNECT";

	public $id;

	public function __construct($id) {
		$this->id = $id;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::DIAGNOSIS)
			return false;

		$ownByMe = new OwnerSpecification(Owner::ME);
		$hand = Controller::instance()->filter($ownByMe);

		foreach ($hand as $sample) {
			if($sample->id == $this->id)
				return true;
		}

		return false;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->id]);
	}
}

class DropAction implements ActionInterface {

	const NAME = "CONNECT";

	public $id;

	public function __construct($id) {
		$this->id = $id;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::DIAGNOSIS)
			return false;

		$ownByMe = new OwnerSpecification(Owner::ME);
		$hand = Controller::instance()->filter($ownByMe);

		foreach ($hand as $sample) {
			if($sample->id == $this->id)
				return true;
		}

		return false;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->id]);
	}
}


class GetDiagnosisAction implements ActionInterface {

	const NAME = "CONNECT";

	public $id;

	public function __construct($id) {
		$this->id = $id;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::DIAGNOSIS)
			return false;

		$inCloud = new OwnerSpecification(Owner::CLOUD);
		$cloud = Controller::instance()->filter($inCloud);

		foreach ($cloud as $sample) {
			if($sample->id == $this->id)
				return true;
		}

		return false;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->id]);
	}
}


class getMoleculeAction implements ActionInterface {

	const NAME = "CONNECT";

	public $molecule;

	public function __construct($molecule) {
		$this->molecule = $molecule;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::MOLECULES)
			return false;

		return Controller::instance()->available[$this->molecule] > 0;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->molecule]);
	}
}

class SendAction implements ActionInterface {

	const NAME = "CONNECT";

	public $id;

	public function __construct($id) {
		$this->id = $id;
	}

	public function isValid(){
		if(Controller::instance()->me->target !== Location::LABORATORY)
			return false;

		$ownByMe = new OwnerSpecification(Owner::ME);
		$hand = Controller::instance()->filter($ownByMe);

		foreach ($hand as $sample) {
			if($sample->id == $this->id)
				return true;
		}

		return false;
	}

	public function get() {
		return implode(" ", [self::NAME, $this->id]);
	}
}




abstract class Molecule {
	
	const A = "A";
	const B = "B";
	const C = "C";
	const D = "D";
	const E = "E";

	public static $reflector = null;

	public static function each($callback) {
		if(is_null(self::$reflector))
			self::$reflector = new ReflectionClass(get_called_class());

		$data = [];
		foreach (self::$reflector->getConstants() as $molecule) {
			$data[] = $callback($molecule);
		}
		return $data;
	}
}


abstract class Owner {

	const CLOUD = -1;
	const ME = 0;
	const OPPONENT = 1;
}


abstract class Location {
	
	const SAMPLES = "SAMPLES";
	const DIAGNOSIS = "DIAGNOSIS";
	const MOLECULES = "MOLECULES";
	const LABORATORY = "LABORATORY";
}

class Robot {

	public $target;
	public $eta;
	public $score;
	public $storage = [];
	public $expertises = [];


	public function __construct() {
		fscanf(STDIN, "%s %d %d %d %d %d %d %d %d %d %d %d %d",
			$this->target,
			$this->eta,
			$this->score,
			$this->storage['A'],
			$this->storage['B'],
			$this->storage['C'],
			$this->storage['D'],
			$this->storage['E'],
			$this->expertises['A'],
			$this->expertises['B'],
			$this->expertises['C'],
			$this->expertises['D'],
			$this->expertises['E']
		);
	}


	public function totalExpertises() {
		return array_sum($this->expertises);
	}


	public function findSamples() {

		$levels = [1, 1, 1];

		if($this->totalExpertises() >= 3)
			$levels = [2, 2, 1];

		if($this->totalExpertises() >= 5)
			$levels = [3, 2, 2];

		if($this->totalExpertises() >= 8)
			$levels = [3, 3, 2];

		$ownByMe = new OwnerSpecification(Owner::ME);
		$samples = Controller::instance()->filter($ownByMe);
		$count = count($samples);

		if($count < 3) {
			$this->perform(new MoveAction(Location::SAMPLES));
			$this->perform(new GetSampleAction($levels[0]));

			if ($count <= 1) $this->perform(new GetSampleAction($levels[1]));
			if ($count == 0) $this->perform(new GetSampleAction($levels[2]));
			
			return true;
		}

		return false;
	}

	
	public function diagnosisSamples() {

		$ownByMe = new OwnerSpecification(Owner::ME);
		$isUndiagnosed = new NotSpecification(new DiagnosedSpecification());

		$spec = new AndSpecification([$ownByMe, $isUndiagnosed]);
		$samples = Controller::instance()->filter($spec);

		if (!empty($samples)) {
			$this->perform(new MoveAction(Location::DIAGNOSIS));
			foreach ($samples as $sample) {
				$this->perform(new DiagnosisAction($sample->id));
			}

			return true;
		}

		return false;
	}


	public function getBestDiagnosis() {

		$ownByMe = new OwnerSpecification(Owner::ME);
		$isDiagnosed = new DiagnosedSpecification();
		$availableForMe = new NotSpecification(new OwnerSpecification(Owner::OPPONENT));
		$isFeasible = new FeasibilitySpecification($this);


		$spec = new AndSpecification([$ownByMe, $isDiagnosed]);
		$hand = Controller::instance()->filter($spec);

		if (count($hand) === 3) {
			
			$spec = new AndSpecification([$availableForMe, $isDiagnosed, $isFeasible]);
			$samples = Controller::instance()->filter($spec);
			$samples = array_slice($samples, 0, 3);


			$diff = array_udiff($hand, $samples, function($a, $b){ 
				return $a->id - $a->id;
			});

			error_log(var_export($diff, true));

			if(!empty($diff)) {
				$this->perform(new MoveAction(Location::DIAGNOSIS));

				// remove hand
				foreach ($hand as $sample) {
					if(!isset($samples[$sample->id]))
						$this->perform(new DropAction($sample->id));
				}

				foreach ($samples as $sample) {
					if($sample->carriedBy == Owner::CLOUD)
						$this->perform(new GetDiagnosisAction($sample->id));
				}

				return true;
			}

		}


		return false;
	}


	public function feasibilityStudy() {
		$controller = Controller::instance();

		$ownByMe = new OwnerSpecification(Owner::ME);
		$isOverelaborate = new NotSpecification(new FeasibilitySpecification($this));
		$notReady = new NotSpecification(new ReadySpecification($this));
		$available = new AvailabilitySpecification();
		
		$spec = new AndSpecification([$ownByMe, $isOverelaborate, $notReady, $available]);
		$samples = $controller->filter($spec);
		
		if (!empty($samples)) {
			$this->perform(new MoveAction(Location::DIAGNOSIS));
			foreach ($samples as $sample) {
				
				$this->perform(new DropAction($sample->id));
				//$sample->carriedBy = -1;
				$controller->sample[$sample->id] = $sample;
			}

			return true;
		}

		return false;
	}


	// On prend les molécules du diagnostique
	public function getMolecules() {

		$ownByMe = new OwnerSpecification(Owner::ME);
		$isReady = new ReadySpecification($this);
		$isDiagnosed = new DiagnosedSpecification();
		$isFeasible = new FeasibilitySpecification($this);
		$isAvailable = new AvailabilitySpecification(false);

		$spec = new AndSpecification([$ownByMe, $isReady]);
		$readySamples = Controller::instance()->filter($spec);

		$spec = new AndSpecification([$ownByMe, $isDiagnosed, $isFeasible, $isAvailable]);
		$samples = Controller::instance()->filter($spec);

		if(!empty($samples) and empty($readySamples)) {
			$this->perform(new MoveAction(Location::MOLECULES));

			$sample = reset($samples);
			$costs = Molecule::each(function($molecule) use ($sample) {
				for ($i=0; $i < $sample->getCost($molecule); $i++) {
					$this->perform(new getMoleculeAction($molecule));
				}
			});

			return true;
		}

		return false;
	}


	// On apporte les molécules au laboratoire
	public function sendLaboratory() {

		$ownByMe = new OwnerSpecification(Owner::ME);
		$isReady = new ReadySpecification($this);

		$spec = new AndSpecification([$ownByMe, $isReady]);
		$samples = Controller::instance()->filter($spec);

		usort($samples, function($a, $b) {
			return ($a->performance() < $b->performance()) ? 1 : -1;
		});

		if(!empty($samples)) {
			$this->perform(new MoveAction(Location::LABORATORY));
			
			$this->perform(new SendAction(reset($samples)->id));
			return true;
		}

		return false;
	}

	public function perform($action) {
		Controller::instance()->addAction($action);
	}

}


class Sample {

	public $id;
	public $carriedBy;
	public $rank;
	public $expertiseGain;
	public $health;
	public $cost;
	
	function __construct() {
		fscanf(STDIN, "%d %d %d %s %d %d %d %d %d %d",
			$this->id,
			$this->carriedBy,
			$this->rank,
			$this->expertiseGain,
			$this->health,
			$this->cost['A'],
			$this->cost['B'],
			$this->cost['C'],
			$this->cost['D'],
			$this->cost['E']
		);
	}


	public function getCost($molecule, $storage = true) {
		$me = Controller::instance()->me;

		$cost = $this->cost[$molecule] - $me->expertises[$molecule];

		if($storage) $cost -= $me->storage[$molecule];

		return $cost > 0 ? $cost : 0;
	}


	public function totalCost($storage = true) {

		$costs = Molecule::each(function($molecule) use ($storage) {
			return $this->getCost($molecule, $storage);
		});

		return array_sum($costs);
	}


	public function performance() {
		return $this->health - $this->totalCost() + Controller::instance()->getSciencePerformance($this->expertiseGain);
	}

}

class ScienceProject {

	public $cost;
	public $expertises = [];
	public $point = 30;


	public function __construct() {
		$this->fetchExpertises();
	}

	private function fetchExpertises() {
		fscanf(STDIN, "%d %d %d %d %d",
			$this->expertises[Molecule::A],
			$this->expertises[Molecule::B],
			$this->expertises[Molecule::C],
			$this->expertises[Molecule::D],
			$this->expertises[Molecule::E]
		);
	}


	public function isMaster($molecule) {
		return $this->expertises[$molecule] - Controller::instance()->me->expertises[$molecule] < 1;
	}


	public function performance($molecule) {
		if (empty($molecule)) return 0;

		if ($this->isMaster($molecule)) return 0;

		$me = Controller::instance()->me;
		$expertises = array_map(function($molecule, $value) use ($me) {
			return $value > $me->expertises[$molecule]
				? $value - $me->expertises[$molecule]
				: 0;
		}, array_keys($this->expertises), $this->expertises);

		return $this->point / array_sum($expertises);
	}

}


class Controller {

	public $available = [];
	public $availableTotal = [];

	private static $_instance;

	private $_actions;

	public $sampleCount;
	public $samples = [];
	
	public $scienceProjects = [];

	public $me;
	public $opponent;


	public $firstRun = true;


	public static function instance() {
		if (is_null(self::$_instance))
			self::$_instance = new Controller();
		
		return self::$_instance;
	}
	
	private function __construct() {
		$this->fetchScienceProjects();
	}


	public function run() {

		$this->me = new Robot();
		$this->opponent = new Robot();


		$this->fetchAvailable();
		$this->fetchSamples();

		if ($this->me->eta > 0)
			return "WAIT";

		if(!$this->hasAction()
			and !$this->me->feasibilityStudy()
			and !$this->me->getBestDiagnosis()
			and !$this->me->diagnosisSamples()
			and !$this->me->sendLaboratory()
			and !$this->me->getMolecules()
			and !$this->me->findSamples()

		) return "WAIT";

		return $this->getAction();
	}
	
	
	private function fetchScienceProjects() {
		fscanf(STDIN, "%d", $projectCount);
		
		for ($i = 0; $i < $projectCount; $i++) {	
			$this->scienceProjects[] = new ScienceProject();
		}
	}


	private function fetchAvailable() {
		fscanf(STDIN, "%d %d %d %d %d",
			$this->available['A'],
			$this->available['B'],
			$this->available['C'],
			$this->available['D'],
			$this->available['E']
		);

		if ($this->firstRun) {
			$this->availableTotal = $this->available;
			$this->firstRun = false;
		}
	}


	private function fetchSamples() {
		
		fscanf(STDIN, "%d", $this->sampleCount);

		$this->samples = [];
		for ($i = 0; $i < $this->sampleCount; $i++) {

			$sample = new Sample();
			$this->samples[] = $sample;
		}

	}


	public function hasAction() {
		return !empty($this->_actions);
	}

	public function addAction($action) {
		$this->_actions[] = $action;
	}

	public function getAction() {

		if(empty($this->_actions)) return "WAIT";

		$action = array_shift($this->_actions);

		if ($action->isValid()) {
			return $action->get();
		}

		error_log(var_export("invalid " . get_class($action) . " : ".$action->get(), true));

		return $this->getAction();
	}



	public function getSciencePerformance($molecule) {
		$performance = array_map(function($project) use ($molecule) {
			return $project->performance($molecule);
		}, $this->scienceProjects);

		return array_sum($performance);
	}



	public function filter($specification) {
		$samples = array_filter($this->samples, function($sample) use ($specification) {
			return $specification->isSatisfiedBy($sample);
		});

		usort($samples, function($a, $b) {
			return ($a->performance() < $b->performance()) ? 1 : -1;
		});

		return $samples;
	}

}



$controller = Controller::instance();

while (TRUE)
{
	echo $controller->run();
	echo "\n";
}


// to do
// find best
// fix wait infinite
// execute only one action

// vérifie AvailabilitySpecification

// To debug
// error_log(var_export($var, true));
?>