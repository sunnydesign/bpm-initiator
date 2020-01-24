# Changelog

BPM Initiator on PHP. Using to start business process in Camunda BPM.

## [Unreleased]

### Planned

- Nothing

## [0.3] - 2020-01-24

### Changed 

- Version kubia/camunda from 0.2 to 0.3

### Added

- Logging to elasticsearch

## [0.2] - 2020-01-17

### Added

- Inheritance from CamundaBaseConnector
- Check running process instances with current business key
- Examples for test send synchronous and asynchronous request for initialize process

## [0.1] - 2019-12-20

### Added

- First worked version on initiator
- Docker environment
- README and CHANGELOG
- Detailed logging
- All ticks timeout moved to config
- Format transit messages from Rabbit MQ changed from `String` to `Json`

[unreleased]: https://gitlab.com/quancy-core/bpm-initiator/-/tags/0.3
[0.3]: https://gitlab.com/quancy-core/bpm-initiator/-/tags/0.3
[0.2]: https://gitlab.com/quancy-core/bpm-initiator/-/tags/0.2
[0.1]: https://gitlab.com/quancy-core/bpm-initiator/-/tags/0.1
