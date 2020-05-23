<?php
/**
 * GravatarExtension.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:Gravatar!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           05.04.14
 */

declare(strict_types = 1);

namespace IPub\Gravatar\DI;

use Nette;
use Nette\Bridges;
use Nette\DI;

use IPub\Gravatar;
use IPub\Gravatar\Caching;
use IPub\Gravatar\Templating;

/**
 * Gravatar extension container
 *
 * @package        iPublikuj:Gravatar!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class GravatarExtension extends DI\CompilerExtension
{
	/**
	 * @var array
	 */
	private $defaults = [
		'expiration'   => 172800,
		'size'         => 80,
		'defaultImage' => FALSE
	];

    /**
     * @return Nette\Schema\Schema
     */
	public function getConfigSchema(): Nette\Schema\Schema {
        return Nette\Schema\Expect::structure([
            'expiration' => Nette\Schema\Expect::int($this->defaults['expiration']),
            'size' => Nette\Schema\Expect::int($this->defaults['size']),
            'defaultImage' => Nette\Schema\Expect::bool($this->defaults['defaultImage']),
        ])->castTo('array');
    }

    /**
	 * @return void
	 */
	public function loadConfiguration() : void
	{
		// Get container builder
		$builder = $this->getContainerBuilder();
		// Get extension configuration
		$configuration = $this->getConfig();

		// Install Gravatar service
		$builder->addDefinition($this->prefix('gravatar'))
			->setType(Gravatar\Gravatar::class)
			->addSetup('setSize', [$configuration['size']])
			->addSetup('setExpiration', [$configuration['expiration']])
			->addSetup('setDefaultImage', [$configuration['defaultImage']]);

		// Create cache services
		$builder->addDefinition($this->prefix('cache'))
			->setType(Caching\Cache::class)
			->setArguments(['@cacheStorage', 'IPub.Gravatar'])
            ->addTag(DI\Extensions\InjectExtension::TAG_INJECT);

		// Register template helpers
		$builder->addDefinition($this->prefix('helpers'))
			->setType(Templating\Helpers::class)
			->setFactory($this->prefix('@gravatar') . '::createTemplateHelpers')
            ->addTag(DI\Extensions\InjectExtension::TAG_INJECT);
			//->setInject(FALSE);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile() : void
	{
		parent::beforeCompile();

		// Get container builder
		$builder = $this->getContainerBuilder();

		// Install extension latte macros
		$latteFactory = $builder->getDefinition($builder->getByType(Bridges\ApplicationLatte\ILatteFactory::class) ?: 'nette.latteFactory');

		$latteFactory
            ->getResultDefinition()
			->addSetup('IPub\Gravatar\Latte\Macros::install(?->getCompiler())', ['@self'])
			->addSetup('addFilter', ['gravatar', [$this->prefix('@helpers'), 'gravatar']])
			->addSetup('addFilter', ['getGravatarService', [$this->prefix('@helpers'), 'getGravatarService']]);
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(Nette\Configurator $config, string $extensionName = 'gravatar') : void
	{
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) : void {
			$compiler->addExtension($extensionName, new GravatarExtension());
		};
	}
}
