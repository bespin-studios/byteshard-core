# [2.7.0](https://github.com/bespin-studios/byteshard-core/compare/v2.6.0...v2.7.0) (2025-03-14)


### Features

* add some configuration options to pdf elements ([76d9777](https://github.com/bespin-studios/byteshard-core/commit/76d9777d4cbebfb13d082a98d5acb67c1bf2277f))

# [2.6.0](https://github.com/bespin-studios/byteshard-core/compare/v2.5.4...v2.6.0) (2025-02-13)


### Bug Fixes

* remove property content array and replace by individual properties ([51a4778](https://github.com/bespin-studios/byteshard-core/commit/51a47788e83815a038ec34877d5833795d558759))
* remove property content array and replace by individual properties ([0b65741](https://github.com/bespin-studios/byteshard-core/commit/0b65741fc5edb4e9f4d5eb6388649501140552b2))


### Features

* add abstract class DynamicCellContent ([6aca131](https://github.com/bespin-studios/byteshard-core/commit/6aca131d5b26c8b1e94745d0c9f8b65439428773))

## [2.5.4](https://github.com/bespin-studios/byteshard-core/compare/v2.5.3...v2.5.4) (2025-02-11)


### Bug Fixes

* add trusted parameter to skip qualifiedName escaping ([1cb063d](https://github.com/bespin-studios/byteshard-core/commit/1cb063d643fddb19a873704fcf4af3fd97d5552f))

## [2.5.3](https://github.com/bespin-studios/byteshard-core/compare/v2.5.2...v2.5.3) (2025-02-11)


### Bug Fixes

* return child on addChildCData ([eafa4ef](https://github.com/bespin-studios/byteshard-core/commit/eafa4efa73f5757b832def881e8abbd91f68bc71))

## [2.5.2](https://github.com/bespin-studios/byteshard-core/compare/v2.5.1...v2.5.2) (2025-01-30)


### Bug Fixes

* add a getter to get environment config object ([6f6d4f4](https://github.com/bespin-studios/byteshard-core/commit/6f6d4f43df79ccc519eec4177c0d2cd29685fd37))

## [2.5.1](https://github.com/bespin-studios/byteshard-core/compare/v2.5.0...v2.5.1) (2025-01-10)


### Bug Fixes

* top level tabs were not selected correctly after logout ([613b948](https://github.com/bespin-studios/byteshard-core/commit/613b948af571726c61339e6fc5b2f8bd1e6e1440))

# [2.5.0](https://github.com/bespin-studios/byteshard-core/compare/v2.4.4...v2.5.0) (2025-01-10)


### Features

* navigationItem for SideBar ([947eaa7](https://github.com/bespin-studios/byteshard-core/commit/947eaa75c50b716d0598c632db96aedc4ba574e1))

## [2.4.4](https://github.com/bespin-studios/byteshard-core/compare/v2.4.3...v2.4.4) (2024-12-26)


### Bug Fixes

* onLinkClick was not working with setSelectedId ([249e71f](https://github.com/bespin-studios/byteshard-core/commit/249e71f82c15a5889a8f27c10534dba005c1d473))

## [2.4.3](https://github.com/bespin-studios/byteshard-core/compare/v2.4.2...v2.4.3) (2024-11-21)


### Bug Fixes

* unselect tabs on a sibling level where the children tab is selected ([7b271d5](https://github.com/bespin-studios/byteshard-core/commit/7b271d5eaac313a71e64326bc355f2b7bda2c586))
* use type instead of mixed ([29e44e4](https://github.com/bespin-studios/byteshard-core/commit/29e44e4325587298edeff59c43d3dfc3887df2db))

## [2.4.2](https://github.com/bespin-studios/byteshard-core/compare/v2.4.1...v2.4.2) (2024-11-08)


### Bug Fixes

* **Deeplink:** strip url context from endpoint to avoid redirect ([6927f94](https://github.com/bespin-studios/byteshard-core/commit/6927f94b14d4f4c26b2d7172e822927aca123efe))
* only strip from start ([175b2c4](https://github.com/bespin-studios/byteshard-core/commit/175b2c43966ac829f13c7430203c86a594850c47))

## [2.4.1](https://github.com/bespin-studios/byteshard-core/compare/v2.4.0...v2.4.1) (2024-10-01)


### Bug Fixes

* deep links should not redirect on internal endpoints ([dd49e46](https://github.com/bespin-studios/byteshard-core/commit/dd49e468002c430a2a828eb6877d957bba9dc852))

# [2.4.0](https://github.com/bespin-studios/byteshard-core/compare/v2.3.0...v2.4.0) (2024-09-27)


### Bug Fixes

* cleanup imports in Environment ([3ac1bb5](https://github.com/bespin-studios/byteshard-core/commit/3ac1bb55d3d8f7eade31ced0f53337fc5abde9bd))
* use interface instead of already existing dummy function in environment for custom provider ([06c096e](https://github.com/bespin-studios/byteshard-core/commit/06c096e17c2fdf4b4977432868767ca99083863a))


### Features

* introduce the possibility to provide custom identity providers ([86646f9](https://github.com/bespin-studios/byteshard-core/commit/86646f91e8d3af74c3c58e64bbfd5c0fd4abd319))

# [2.3.0](https://github.com/bespin-studios/byteshard-core/compare/v2.2.7...v2.3.0) (2024-09-27)


### Bug Fixes

* adjust sameSite cookie settings ([36178bc](https://github.com/bespin-studios/byteshard-core/commit/36178bc1adc53b04b18a78977b8aba0693a47da5))
* formatting ([e2a30e0](https://github.com/bespin-studios/byteshard-core/commit/e2a30e0d675c9872bbbf9b145fb2cc9696c7cff2))
* move Deeplink::checkReferrer() to where it is called with either auth once after login ([738cb92](https://github.com/bespin-studios/byteshard-core/commit/738cb92a30790145c8b198138fedbc4887a874a5))
* remove unused commented out code ([1a55420](https://github.com/bespin-studios/byteshard-core/commit/1a55420be835a8609aecfd4fc5cfdc115644217e))
* revert making authentication action key public ([98a102a](https://github.com/bespin-studios/byteshard-core/commit/98a102ab65e4c7884a3ef518db031edf5fb7b0d3))


### Features

* add support for multiple filters in the same cell ([e99ad5c](https://github.com/bespin-studios/byteshard-core/commit/e99ad5c07c8321254e65a2fb96193f91158f4f60))
* add the possibility to deeplink to an tab via a "tab" get parameter ([e896e2d](https://github.com/bespin-studios/byteshard-core/commit/e896e2da81ee5b7f1174792fb99836998ff08bb6))
* allow additional cell, column and filter parameter ([1766dfb](https://github.com/bespin-studios/byteshard-core/commit/1766dfb65aa8485ba05fedbc73eb5d339cc121d1))

## [2.2.7](https://github.com/byteshard/core/compare/v2.2.6...v2.2.7) (2024-09-11)


### Bug Fixes

* trying to access property before initialization ([7b19512](https://github.com/byteshard/core/commit/7b195128839222af374fd7c1f02546538886f662))

## [2.2.6](https://github.com/byteshard/core/compare/v2.2.5...v2.2.6) (2024-09-11)


### Bug Fixes

* better implementation for date values in form calendars ([29736c1](https://github.com/byteshard/core/commit/29736c1acf77400c5b49a0e579fede506908fde8))

## [2.2.5](https://github.com/byteshard/core/compare/v2.2.4...v2.2.5) (2024-08-30)


### Bug Fixes

* insufficient implementation of button interface ([03c065f](https://github.com/byteshard/core/commit/03c065f0a2c88240407e7359a818f239b02ac1cb))

## [2.2.4](https://github.com/byteshard/core/compare/v2.2.3...v2.2.4) (2024-08-02)


### Bug Fixes

* add check for ClearUpload action during upload evaluation ([387ef54](https://github.com/byteshard/core/commit/387ef54c21bff784e3388f30b1c0e56a39aeb94d))

## [2.2.3](https://github.com/byteshard/core/compare/v2.2.2...v2.2.3) (2024-07-31)


### Bug Fixes

* scheduler entry was not implemented correctly ([d5aefff](https://github.com/byteshard/core/commit/d5aefff94878f404488cc0c4c0da2a416e2d1c72))

## [2.2.2](https://github.com/byteshard/core/compare/v2.2.1...v2.2.2) (2024-07-23)


### Bug Fixes

* files without extension could not be processed by the file object ([bb2323f](https://github.com/byteshard/core/commit/bb2323f7055313fad0829afb0493f767ba776764))

## [2.2.1](https://github.com/byteshard/core/compare/v2.2.0...v2.2.1) (2024-07-18)


### Bug Fixes

* public function in combo to get the url which will be used in ReloadFormObject ([5d77d18](https://github.com/byteshard/core/commit/5d77d1842734c5bf9474fb1632ba5fd54676668e))

# [2.2.0](https://github.com/byteshard/core/compare/v2.1.0...v2.2.0) (2024-07-12)


### Features

* backend implementation for asynchronous combo options ([5c8ef93](https://github.com/byteshard/core/commit/5c8ef9334123465df309e7971fe3c118c78bc074))
* option to set upload to single file mode ([53309ba](https://github.com/byteshard/core/commit/53309ba17c75fcd3dafc70d7a19c47cc57083e5e))

# [2.1.0](https://github.com/byteshard/core/compare/v2.0.0...v2.1.0) (2024-07-11)


### Features

* new way (again) to select combo option (sigh) ([a78c79f](https://github.com/byteshard/core/commit/a78c79ff0bfb5f8e11ef97ff1a00442f30e0b6b3))

# [2.0.0](https://github.com/byteshard/core/compare/v1.11.0...v2.0.0) (2024-07-08)


* Merge pull request #31 from byteshard/lhennig_fixFileSanitationDeprecationWarning ([048b4c1](https://github.com/byteshard/core/commit/048b4c1cc169b1c2e8eb8d1f305d69589b401afd)), closes [#31](https://github.com/byteshard/core/issues/31)


### BREAKING CHANGES

* ext-mbstring needed from now on in favour of neitano…

# [1.11.0](https://github.com/byteshard/core/compare/v1.10.1...v1.11.0) (2024-07-03)


### Bug Fixes

* remove file extension from name ([218a60e](https://github.com/byteshard/core/commit/218a60eceb0ceddb20cfab021fa9a3e9c763ecd6))


### Features

* add shell utils ([8c17c32](https://github.com/byteshard/core/commit/8c17c32adad6987f115b76123f1119b9d359cf40))

## [1.10.1](https://github.com/byteshard/core/compare/v1.10.0...v1.10.1) (2024-06-20)


### Bug Fixes

* upgrade monolog to v3 ([f72c0c5](https://github.com/byteshard/core/commit/f72c0c5d5d9e4a5feeb2f8e25809c70d0b692f3f))

# [1.10.0](https://github.com/byteshard/core/compare/v1.9.0...v1.10.0) (2024-06-20)


### Features

* implement pgsql classmap fetch ([113cab9](https://github.com/byteshard/core/commit/113cab9c229208115f4895c67b54ecb0f3c847f3))

# [1.9.0](https://github.com/byteshard/core/compare/v1.8.3...v1.9.0) (2024-06-20)


### Features

* allow Enums for Permissions ([3ab1cf3](https://github.com/byteshard/core/commit/3ab1cf32b119c5f908e3c0fac4bdfd5ad1096c58))

## [1.8.3](https://github.com/byteshard/core/compare/v1.8.2...v1.8.3) (2024-05-08)


### Bug Fixes

* HTTP_REFERER is not available running within a context, SCRIPT_URI is ([6b007f6](https://github.com/byteshard/core/commit/6b007f690b5c8c8c82147b280d280a6f7ca2b526))

## [1.8.2](https://github.com/byteshard/core/compare/v1.8.1...v1.8.2) (2024-05-06)


### Bug Fixes

* don't try to access db parameters in case they're not used ([64ac8f0](https://github.com/byteshard/core/commit/64ac8f0114fd33ff6fc1429edd759ec5790b4ec7))
* don't try to access db parameters in case they're not used ([b552b8c](https://github.com/byteshard/core/commit/b552b8c4e545e644600a98414992435d497cee09))

## [1.8.1](https://github.com/byteshard/core/compare/v1.8.0...v1.8.1) (2024-05-06)


### Bug Fixes

* give AuthenticationActions a proper name and add logout param ([416e279](https://github.com/byteshard/core/commit/416e2790c83f5d577172a29bbed0546e84ba7707))

# [1.8.0](https://github.com/byteshard/core/compare/v1.7.0...v1.8.0) (2024-04-26)


### Features

* encapsulated login template. Less magic ([1a6c05e](https://github.com/byteshard/core/commit/1a6c05e277a57abf7ae89058a8be4c9d334304e3))

# [1.7.0](https://github.com/byteshard/core/compare/v1.6.0...v1.7.0) (2024-04-24)


### Bug Fixes

* Access to an undefined property UserTable ([b45eb36](https://github.com/byteshard/core/commit/b45eb36b0d6f9d6fefee10a3387913f09914784b))
* incorrect return type in oidc ([a0135b9](https://github.com/byteshard/core/commit/a0135b9740c529c074790f8de83e3909a8fc54b2))
* show previous error in pdo to help debug classMap type exceptions ([f97b14e](https://github.com/byteshard/core/commit/f97b14ec052dcd160dade1cdd4b2ad341d2dd895))


### Features

* oauth support ([e49f705](https://github.com/byteshard/core/commit/e49f70571c8f9b15d7e9b8bd3c8b137dfe01ffcb))

# [1.6.0](https://github.com/byteshard/core/compare/v1.5.0...v1.6.0) (2024-03-22)


### Features

* add simple jwt class ([5acca63](https://github.com/byteshard/core/commit/5acca63c1cf5eeb39f4440bb9b0f71286c105cc8))

# [1.5.0](https://github.com/byteshard/core/compare/v1.4.0...v1.5.0) (2024-03-15)


### Bug Fixes

* add exception message to log for better debugging experience ([dd9d01b](https://github.com/byteshard/core/commit/dd9d01bfda8bf741e05fc7c43e9c7fb8343bbaaf))
* catch bubble exception ([f762e1a](https://github.com/byteshard/core/commit/f762e1a20747958b61483bfa124c99ee032945aa))


### Features

* add implicit events ([8d3265c](https://github.com/byteshard/core/commit/8d3265c9905010475c0ea2938931745a399afbe8))
* add option to enable/disable autoremove on upload controls ([d7dfd95](https://github.com/byteshard/core/commit/d7dfd9556ce44ae97693e360c01b2bfc6b7f2f5a))

# [1.4.0](https://github.com/byteshard/core/compare/v1.3.4...v1.4.0) (2024-03-15)


### Bug Fixes

* stop passing non-existing exception to function ([71fa0b4](https://github.com/byteshard/core/commit/71fa0b4fbba68f8f46e5a7c2f2f9a6de7e994fc2))


### Features

* support for rest api exception handling ([3abc94b](https://github.com/byteshard/core/commit/3abc94b9f76f346970ff390a47f8b943cd278ad3))

## [1.3.4](https://github.com/byteshard/core/compare/v1.3.3...v1.3.4) (2024-02-14)


### Bug Fixes

* use correct exception handling during upload ([3c74ea7](https://github.com/byteshard/core/commit/3c74ea7d7a217435581e7edcd6b0f476cbea6b55))

## [1.3.3](https://github.com/byteshard/core/compare/v1.3.2...v1.3.3) (2024-02-14)


### Bug Fixes

* use correct exception handling during upload ([71a70f7](https://github.com/byteshard/core/commit/71a70f7d9df4651961720948a0a9df37e2ef3d04))

## [1.3.2](https://github.com/byteshard/core/compare/v1.3.1...v1.3.2) (2024-02-13)


### Bug Fixes

* upload used incorrect id and the client crashed ([5fb9ee8](https://github.com/byteshard/core/commit/5fb9ee8faa745c81f03dfa0e7ea0335d52003a15))

## [1.3.1](https://github.com/byteshard/core/compare/v1.3.0...v1.3.1) (2023-12-01)


### Bug Fixes

* remove preceding slashes since get_called_class returns it without it ([eb2a312](https://github.com/byteshard/core/commit/eb2a312a46c9027ddb3e710ee7f89cbd33b0163f))

# [1.3.0](https://github.com/byteshard/core/compare/v1.2.0...v1.3.0) (2023-11-25)


### Features

* add class maps to mysql ([9b0d9e5](https://github.com/byteshard/core/commit/9b0d9e568b953a8579bd57239ae012e7f54e5f94))

# [1.2.0](https://github.com/byteshard/core/compare/v1.1.0...v1.2.0) (2023-11-24)


### Bug Fixes

* add value interface to check if the value trait is implemented in form objects ([0625311](https://github.com/byteshard/core/commit/0625311b5fa7cacc5906d91dda95b3d466dd2247))


### Features

* add additional user data to session in public class ([feb8071](https://github.com/byteshard/core/commit/feb8071d08f95909bf629fef7d1bb64eee7b1fcf))

# [1.1.0](https://github.com/byteshard/core/compare/v1.0.10...v1.1.0) (2023-11-24)


### Features

* defineDataBinding can now be used in forms ([9535ad4](https://github.com/byteshard/core/commit/9535ad4da6ca80c08056c1bcb81167d11fa7dadc))
* defineDataBinding can now be used in forms ([bfe75ff](https://github.com/byteshard/core/commit/bfe75ff9189d811edf58be132b9562de1fc1ad21))

## [1.0.10](https://github.com/byteshard/core/compare/v1.0.9...v1.0.10) (2023-11-16)


### Bug Fixes

* if a confirmAction is returned as part of a callMethod or saveFormData action it can now work within the limits of the onClick event ([5fe9127](https://github.com/byteshard/core/commit/5fe9127a58421d05cd9ea386fb9961823ea86876))

## [1.0.9](https://github.com/byteshard/core/compare/v1.0.8...v1.0.9) (2023-10-31)


### Bug Fixes

* possible Host Header injection in Login page is now fixed. ([4691e6d](https://github.com/byteshard/core/commit/4691e6dabdd1768055a60f9d888cf005a5c8ebd3))

## [1.0.8](https://github.com/byteshard/core/compare/v1.0.7...v1.0.8) (2023-10-12)


### Bug Fixes

* replace utf8 encode/decode with mb_convert_encoding to remove deprecation warning ([4c3ab35](https://github.com/byteshard/core/commit/4c3ab354625f3181350f6ab3f5721179865758f4))

## [1.0.7](https://github.com/byteshard/core/compare/v1.0.6...v1.0.7) (2023-10-09)


### Bug Fixes

* use enum for ColumnType ([451b691](https://github.com/byteshard/core/commit/451b6911892caba2732e3a66aef079462b68dcd7))
* use enum for ColumnType ([816ea61](https://github.com/byteshard/core/commit/816ea61b162fa4769a92b746bc6c6c69c123c33c))

## [1.0.6](https://github.com/byteshard/core/compare/v1.0.5...v1.0.6) (2023-10-09)


### Bug Fixes

* mysql connection was trying to use string instead of enum ([c374cb4](https://github.com/byteshard/core/commit/c374cb4f3611a8e524506b58e37cf30088364337))

## [1.0.5](https://github.com/byteshard/core/compare/v1.0.4...v1.0.5) (2023-08-15)


### Bug Fixes

* catch export exceptions and show error message in client ([ab7bfa1](https://github.com/byteshard/core/commit/ab7bfa169bc41d6abe4d67d52a90f9cd334e5d2c))

## [1.0.4](https://github.com/byteshard/core/compare/v1.0.3...v1.0.4) (2023-07-19)


### Bug Fixes

* updated phpDoc parameter type ([3414ecc](https://github.com/byteshard/core/commit/3414ecc0f6ab69fa1b9b7f0a67165429f1918ad9))

## [1.0.3](https://github.com/byteshard/core/compare/v1.0.2...v1.0.3) (2023-06-06)


### Bug Fixes

* lasttab: column name changed to lowercase: LastTab => lasttab ([15b75a9](https://github.com/byteshard/core/commit/15b75a92906e95dfef5b644f9b7a1592ff3345be))

## [1.0.2](https://github.com/byteshard/core/compare/v1.0.1...v1.0.2) (2023-05-10)


### Bug Fixes

* add missing extensions to composer step as well ([1c4a74b](https://github.com/byteshard/core/commit/1c4a74b2d07101c44ad1b75072cef8fa4e48b66b))

## [1.0.1](https://github.com/byteshard/core/compare/v1.0.0...v1.0.1) (2023-05-10)


### Bug Fixes

* add missing extensions for workflows ([8802a2e](https://github.com/byteshard/core/commit/8802a2e255fea2099a43e13c005e4c1cd939b457))

# 1.0.0 (2023-05-10)


### Features

* initial commit for core ([d89dd26](https://github.com/byteshard/core/commit/d89dd260bd3fae6d6ffbcc275eeb68bfba55c132))
