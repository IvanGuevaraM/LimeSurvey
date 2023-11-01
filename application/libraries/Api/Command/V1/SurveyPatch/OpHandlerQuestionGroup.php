<?php

namespace LimeSurvey\Api\Command\V1\SurveyPatch;

use QuestionGroup;
use LimeSurvey\Models\Services\QuestionGroupService;
use LimeSurvey\Api\Command\V1\Transformer\Input\{
    TransformerInputQuestionGroup,
    TransformerInputQuestionGroupL10ns
};
use LimeSurvey\ObjectPatch\{
    Op\OpInterface,
    OpHandler\OpHandlerException,
    OpHandler\OpHandlerInterface,
    OpType\OpTypeCreate,
    OpType\OpTypeDelete,
    OpType\OpTypeUpdate
};

class OpHandlerQuestionGroup implements OpHandlerInterface
{
    use OpHandlerSurveyTrait;

    protected string $entity;
    protected QuestionGroup $model;
    protected QuestionGroupService $questionGroupService;
    protected TransformerInputQuestionGroup $transformer;
    protected TransformerInputQuestionGroupL10ns $transformerL10n;

    private bool $isUpdateOperation = false;
    private bool $isCreateOperation = false;
    private bool $isDeleteOperation = false;

    public function __construct(
        QuestionGroup $model,
        QuestionGroupService $questionGroupService,
        TransformerInputQuestionGroup $transformer,
        TransformerInputQuestionGroupL10ns $transformerL10n
    ) {
        $this->entity = 'questionGroup';
        $this->model = $model;
        $this->questionGroupService = $questionGroupService;
        $this->transformer = $transformer;
        $this->transformerL10n = $transformerL10n;
    }

    /**
     * Checks if the operation is applicable for the given entity.
     *
     * @param OpInterface $op
     * @return bool
     */
    public function canHandle(OpInterface $op): bool
    {
        $this->setOperationTypes($op);
        $isQuestionGroupEntity = $op->getEntityType() === $this->entity;

        return
            (
                $this->isUpdateOperation
                || $this->isCreateOperation
                || $this->isDeleteOperation
            )
            && $isQuestionGroupEntity;
    }

    /**
     * Saves the changes to the database.
     *
     * @param OpInterface $op
     * @throws OpHandlerException
     */
    public function handle(OpInterface $op): void
    {
        switch (true) {
            case $this->isUpdateOperation:
                $this->update($op);
                break;
            case $this->isCreateOperation:
                $this->create($op);
                break;
            case $this->isDeleteOperation:
                $this->delete($op);
                break;
        }
    }

    /**
     * Reads the operation type from the given operation,
     * and sets the corresponding flags.
     *
     * @param OpInterface $op
     * @return void
     */
    public function setOperationTypes(OpInterface $op)
    {
        $this->isUpdateOperation
            = $op->getType()->getId() === OpTypeUpdate::ID;
        $this->isCreateOperation
            = $op->getType()->getId() === OpTypeCreate::ID;
        $this->isDeleteOperation
            = $op->getType()->getId() === OpTypeDelete::ID;
    }

    /**
     * Makes use of the transformers dependent on the passed structure of props
     *
     * @param OpInterface $op
     * @return array|mixed
     * @throws OpHandlerException
     */
    public function getTransformedProps(OpInterface $op)
    {
        $transformedProps = [];
        $props = $op->getProps();
        if (isset($props['questionGroup'])) {
            $transformedProps['questionGroup'] = $this->transformer->transform(
                $props['questionGroup']
            );
        }
        if (isset($props['questionGroupL10n'])) {
            foreach (
                $props['questionGroupL10n'] as $lang => $questionGroupL10n
            ) {
                $transformedProps['questionGroupI10N'][$lang]
                    = $this->transformerL10n->transform(
                        $questionGroupL10n
                    );
            }
        }

        if (empty($props) || empty($transformedProps)) {
            throw new OpHandlerException(
                sprintf(
                    'No values to update for entity %s',
                    $op->getEntityType()
                )
            );
        }
        return $transformedProps;
    }

    /**
     * For update of a question group the patch should look like this:
     *
     * {
     *    "patch": [
     *         {
     *             "entity": "questionGroup",
     *             "op": "update",
     *             "id": 7,
     *             "props": {
     *                 "questionGroup": {
     *                     "randomizationGroup": "",
     *                     "gRelevance": ""
     *                 },
     *                 "questionGroupL10n": {
     *                     "en": {
     *                         "groupName": "3rd Group - updated",
     *                         "description": "English"
     *                     },
     *                     "fr": {
     *                         "groupName": "Troisième Groupe - updated",
     *                         "description": "French"
     *                     }
     *                 }
     *             }
     *         }
     *     ]
     * }
     *
     * @param OpInterface $op
     * @param QuestionGroupService $groupService
     * @return void
     * @throws OpHandlerException
     * @throws \LimeSurvey\Models\Services\Exception\NotFoundException
     * @throws \LimeSurvey\Models\Services\Exception\PermissionDeniedException
     * @throws \LimeSurvey\Models\Services\Exception\PersistErrorException
     */
    private function update(OpInterface $op)
    {
        $surveyId = $this->getSurveyIdFromContext($op);
        $transformedProps = $this->getTransformedProps($op);
        $questionGroup = $this->questionGroupService->getQuestionGroupForUpdate(
            $surveyId,
            $this->getQuestionGroupId($op)
        );
        if (isset($transformedProps['questionGroup'])) {
            $this->questionGroupService->updateQuestionGroup(
                $questionGroup,
                $transformedProps['questionGroup']
            );
        }
        if (isset($transformedProps['questionGroupI10N'])) {
            $this->questionGroupService->updateQuestionGroupLanguages(
                $questionGroup,
                $transformedProps['questionGroupI10N']
            );
        }
    }

    /**
     * To fully create a new question group, the dataset should have
     * this structure for props:
     *
     * {
     *     "patch": [
     *         {
     *             "entity": "questionGroup",
     *             "op": "create",
     *             "props":{
     *                 "questionGroup": {
     *                     "randomizationGroup": "",
     *                     "gRelevance": ""
     *                 },
     *                 "questionGroupL10n": {
     *                     "en": {
     *                         "groupName": "3rd Group",
     *                         "description": "English"
     *                     },
     *                     "fr": {
     *                         "groupName": "Troisième Groupe",
     *                         "description": "French"
     *                     }
     *                 }
     *             }
     *         }
     *     ]
     * }
     *
     * If those questionGroup and questionGroupL10n properties are missing,
     * and the structure resembles the usual update structure,
     * only a basic question group will be created. Language specific data must
     * then be passed in a different patch operation.
     *
     * @param OpInterface $op
     * @param QuestionGroupService $groupService
     * @return void
     * @throws OpHandlerException
     * @throws \LimeSurvey\Models\Services\Exception\NotFoundException
     * @throws \LimeSurvey\Models\Services\Exception\PersistErrorException
     */
    private function create(OpInterface $op)
    {
        $surveyId = $this->getSurveyIdFromContext($op);
        $transformedProps = $this->getTransformedProps($op);
        $this->questionGroupService->createGroup(
            $surveyId,
            $transformedProps
        );
    }

    /**
     * To delete a question group, the dataset should look like this
     * {
     *    "patch": [
     *        {
     *            "entity": "questionGroup",
     *            "op": "delete",
     *            "id": 7
     *        }
     *    ]
     * }
     *
     * @param OpInterface $op
     * @param QuestionGroupService $groupService
     * @return void
     */
    private function delete(OpInterface $op)
    {
        $surveyId = $this->getSurveyIdFromContext($op);
        $this->questionGroupService->deleteGroup(
            $this->getQuestionGroupId($op),
            $surveyId
        );
    }

    /**
     * Extracts and returns gid (question group id) from passed id parameter
     *
     * @param OpInterface $op
     * @return int
     * @throws OpHandlerException
     **/
    private function getQuestionGroupId(OpInterface $op)
    {
        $id = $op->getEntityId();
        if (!isset($id)) {
            throw new OpHandlerException('No group id provided');
        }
        return $id;
    }

    /**
     * Checks if patch is valid for this operation.
     * @param OpInterface $op
     * @return bool
     */
    public function isValidPatch(OpInterface $op): bool
    {
        // TODO: Implement isValidPatch() method.
        return true;
    }
}
