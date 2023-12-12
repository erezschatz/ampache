<?php

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2023
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=0);

namespace Ampache\Module\Application\Share;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Module\Util\RequestParserInterface;
use Ampache\Module\Util\ZipHandlerInterface;
use Ampache\Repository\Model\Album;
use Ampache\Repository\Model\AlbumDisk;
use Ampache\Repository\Model\Playlist;
use Ampache\Repository\Model\Share;
use Ampache\Module\Application\ApplicationActionInterface;
use Ampache\Module\Application\Exception\AccessDeniedException;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\User\PasswordGeneratorInterface;
use Ampache\Module\Util\ObjectTypeToClassNameMapper;
use Ampache\Module\Util\UiInterface;
use Ampache\Repository\Model\Song;
use Ampache\Repository\Model\Video;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ShowCreateAction implements ApplicationActionInterface
{
    public const REQUEST_KEY = 'show_create';

    private RequestParserInterface $requestParser;

    private ConfigContainerInterface $configContainer;

    private UiInterface $ui;

    private PasswordGeneratorInterface $passwordGenerator;

    private ZipHandlerInterface $zipHandler;

    public function __construct(
        RequestParserInterface $requestParser,
        ConfigContainerInterface $configContainer,
        UiInterface $ui,
        PasswordGeneratorInterface $passwordGenerator,
        ZipHandlerInterface $zipHandler
    ) {
        $this->requestParser     = $requestParser;
        $this->configContainer   = $configContainer;
        $this->ui                = $ui;
        $this->passwordGenerator = $passwordGenerator;
        $this->zipHandler        = $zipHandler;
    }

    public function run(ServerRequestInterface $request, GuiGatekeeperInterface $gatekeeper): ?ResponseInterface
    {
        if (!$this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHARE)) {
            throw new AccessDeniedException('Access Denied: sharing features are not enabled.');
        }

        $this->ui->showHeader();

        $object_type = Share::is_valid_type($this->requestParser->getFromRequest('type'));
        $object_id   = $this->requestParser->getFromRequest('id');
        if (!empty($object_type) && !empty($object_id)) {
            if (is_array($object_id)) {
                $object_id = $object_id[0];
            }

            $className = ObjectTypeToClassNameMapper::map($object_type);
            /** @var Song|Album|AlbumDisk|Playlist|Video $object */
            $object = new $className($object_id);
            if ($object->isNew() === false) {
                $token     = $this->passwordGenerator->generate_token();
                $isZipable = $this->zipHandler->isZipable($object_type);
                $object->format();
                $this->ui->show(
                    'show_add_share.inc.php',
                    [
                        'has_failed' => false,
                        'message' => '',
                        'object' => $object,
                        'object_type' => $object_type,
                        'token' => $token,
                        'isZipable' => $isZipable
                    ]
                );
            }
        }
        $this->ui->showFooter();

        return null;
    }
}
