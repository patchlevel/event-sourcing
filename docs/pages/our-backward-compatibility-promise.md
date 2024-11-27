# Our Backward Compatibility Promise

Our top priority is to ensure that upgrading your projects is seamless. To support this, we guarantee backward
compatibility (BC) for all minor releases. This approach aligns with the principles
of [Semantic Versioning](https://semver.org/). In essence, Semantic Versioning dictates that only major releases (like
5.0, 6.0, etc.) can introduce changes that break backward compatibility. Minor releases (such as 5.1, 5.2, etc.) may add
new features but must maintain the existing API structure of that version branch (for example, 5.x).

To monitor if PRs are introducing BC breaks, we are
using [roave/backward-compatibility-check](https://github.com/Roave/BackwardCompatibilityCheck) in our CI. This great
tool notifies us if a BC break would be introduced by merging a given PR. It makes preventing these breaks in minor or
patch versions so much easier. Big kudos to the [roave](https://roave.com/) team for this tool!

If we know that we will break bc in the next major version, we will include deprecation messages in the code base to
assist with the transition to the next major release.

Not all BC breaks impact application code in the same way. While some require substantial changes to your classes or
architecture, others may only involve minor adjustments, such as renaming a method.

## Exceptions

### Internal

Some our classes or methods may be marked with `@internal`. These are then not covered by our backward compatibility
promise. This is done to ease the development for us and should not impact you as a user in any kind. At the time of
writing this hold only true for one of our classes.

### Named Arguments

There is also one exception we are doing regarding our BC policy: Name arguments. These are only covered for
constructor parameters of our Attributes. Using PHP named arguments might break your code when upgrading to newer
versions.

### Experimental Features

Sometimes we want to introduce more complex features or feature where we are not sure how the API should look like. In
these cases we will mark the classes/methods as `@experimental`. We are doing this to get a better grip on how the
feature could look like and to retrieve more feedback on them. Doing this period they are not covered by our backward
compatibility promise.

In our docs the features are marked like this:

??? example "Experimental"

    This feature is still experimental and may change in the future.
    Use it with caution.
