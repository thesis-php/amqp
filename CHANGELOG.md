# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] 2025-06-07

### Changed

- Refactor integration with `DeliverySupervisor`.
- Replace `Amp\Future` with `Thesis\Sync` on `Delivery` methods.
- Use `Thesis\TimeSpan`.
- Fix channel garbage collection.
- Hooks refactoring.
- Allow to call `Channel::get` concurrently.
- Allow to call `Channel::close` concurrently.
- Fix iterator complete.
- Improve documentation.
- Clear all Channel and Client state on `close/disconnect`.

### Added

- RPC implemented.

## [0.4.0] 2025-05-06

### Changed

- Improve title and description in README.
- Reorder `Channel` properties.
- Sync codebase with the latest Thesis template and update dev dependencies.
- Apply Rector fixes.
- Make `Client::$config` property public.

### Added

- Experimental batch consume implemented.
- Returns should be handled as callbacks and explicitly in confirmation mode enabled.
- Improve `PublishBatchConfirmationResult::ok()`.
- Use `TimeoutCancellation` instead of `EventLoop::delay` in `BatchConsumer`.

## [0.3.1] 2025-04-14

### Changed

- Implicit connection on `Client::channel()`.
- Allow concurrent and exclusive call to `DeliveryMessage::ack()`, `DeliveryMessage::nack()` or `DeliveryMessage::reject()`.

## [0.3.0] 2025-04-12

### Added

- Support cluster config.
- Added batch api for publish.

### Changed

- Enable `tcp_nodelay` by default.
- Add `Thesis\Amqp\Channel::isClosed()`.
- Use PascalCase for `ChannelMode` cases.
- Use `Thesis\Amqp\Message` in `Thesis\Amqp\Delivery`.
- Rename `Thesis\Amqp\Confirmation` to `Thesis\Amqp\PublishConfirmation`.

## [0.2.0] 2025-03-23

### Changed

- Use `DateTimeImmutable` instead of `DateTimeInterface` in all signatures.
- Disconnect client in destructor if PHP >= 8.4.
- Do not throw in `Client::disconnect()` if client is not connected.

### Fixed

- Allow to use `Confirmation::awaitAll()` without iteration.
